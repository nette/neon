<?php declare(strict_types=1);

/**
 * Test: the Evaluator and its hooks.
 */

use Nette\Neon\{Entity, Evaluator, Exception, Neon, Place};
use Nette\Neon\Nodes\{ArrayItemNode, EntityChainNode, EntityNode, LiteralNode, StringNode};
use Tester\Assert;
use Tests\Dumper;

require __DIR__ . '/../bootstrap.php';


test('the default projection is the one of decode', function () {
	$input = "a: [1, x: 2, 3]\nb: f(c, d: e)g\n0x10: 2020-01-02\n";
	$document = Neon::parse($input);
	Assert::equal(Neon::decode($input), $document->toValue());
	Assert::equal([0 => 1, 'x' => 2, 1 => 3], $document->value?->items[0]->value?->toValue());
	Assert::equal(new Entity(Neon::Chain, [new Entity('f', ['c', 'd' => 'e']), new Entity('g')]), $document->toValue()['b']);
	Assert::notSame($document->toValue()['b'], $document->toValue()['b']);
	Assert::same([16], array_keys((array) Neon::decode('0x10: a')));
});


test('the hooks get the place, and the tree stays as it was', function () {
	$evaluator = new class extends Evaluator {
		/** @var list<string> */
		public array $log = [];


		protected function evaluateLiteral(LiteralNode $node, Place $place): mixed
		{
			$this->log[] = $place->name . ' ' . $node->token->text;
			return $place === Place::Value && defined($node->token->text)
				? constant($node->token->text)
				: parent::evaluateLiteral($node, $place);
		}


		protected function evaluateString(StringNode $node, Place $place): mixed
		{
			$this->log[] = $place->name . ' ' . $node->token->text;
			return parent::evaluateString($node, $place);
		}


		protected function evaluateEntity(EntityNode $node, Place $place): mixed
		{
			$this->log[] = $place->name . ' entity';
			return parent::evaluateEntity($node, $place);
		}
	};

	$document = Neon::parse("k: PHP_INT_SIZE\n'q': f(a)g\n");
	$dump = Dumper::dump($document);
	Assert::equal(
		['k' => PHP_INT_SIZE, 'q' => new Entity(Neon::Chain, [new Entity('f', ['a']), new Entity('g')])],
		$evaluator->evaluate($document->value),
	);
	Assert::same([
		'Key k', 'Value PHP_INT_SIZE',
		"Key 'q'", 'ChainLink entity', 'EntityHead f', 'Value a', 'ChainLink entity', 'EntityHead g',
	], $evaluator->log);
	Assert::same($dump, Dumper::dump($document));
});


test('a chain can be projected as a whole, a nested one too', function () {
	$evaluator = new class extends Evaluator {
		protected function evaluateChain(EntityChainNode $node): mixed
		{
			return array_map(fn(EntityNode $link) => $this->evaluateEntity($link, Place::ChainLink), $node->entities);
		}
	};
	Assert::equal(
		['a' => [new Entity('f', [1]), new Entity('g')], 'b' => new Entity('x', [[new Entity('y'), new Entity('z')]])],
		$evaluator->evaluate(Neon::parse("a: f(1)g\nb: x(y()z)")->value),
	);
});


test('an item can be projected, and its keys are those of the document', function () {
	$evaluator = new class extends Evaluator {
		protected function evaluateItem(ArrayItemNode $item): mixed
		{
			return $item->value instanceof LiteralNode && $item->value->token->text === '_'
				? null
				: parent::evaluateItem($item);
		}
	};
	Assert::same([0 => null, 1 => 'b', 'c' => 'd'], $evaluator->evaluate(Neon::parse('[_, b, c: d]')->value));
});


test('two keys of the document projected to one key fail, a key repeated in the document does not', function () {
	$evaluator = new class extends Evaluator {
		protected function evaluateLiteral(LiteralNode $node, Place $place): mixed
		{
			return $place === Place::Key ? strtolower($node->token->text) : parent::evaluateLiteral($node, $place);
		}
	};
	Assert::exception(
		fn() => $evaluator->evaluate(Neon::parse("A: 1\na: 2")->value),
		Exception::class,
		"Duplicated key 'a' on line 2 at column 1",
	);
	Assert::same([0 => 'x', 1 => 'y'], $evaluator->evaluate(Neon::parse('[a, 0: x, y]')->value));

	$invalid = new class extends Evaluator {
		protected function evaluateLiteral(LiteralNode $node, Place $place): mixed
		{
			return $place === Place::Key ? 1.5 : parent::evaluateLiteral($node, $place);
		}
	};
	Assert::exception(
		fn() => $invalid->evaluate(Neon::parse('a: 1')->value),
		LogicException::class,
		'The key of an item must be int or string, float given.',
	);
});
