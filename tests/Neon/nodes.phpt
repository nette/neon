<?php declare(strict_types=1);

/**
 * Test: nodes of the tree, built by hand: ownership, clone and the journal.
 */

use Nette\Neon\Journal;
use Nette\Neon\Neon;
use Nette\Neon\Nodes\ArrayItemNode;
use Nette\Neon\Nodes\DocumentNode;
use Nette\Neon\Nodes\InlineArrayNode;
use Nette\Neon\Nodes\LiteralNode;
use Nette\Neon\Token;
use Nette\Neon\Trivia;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


function literal(string $text): LiteralNode
{
	return new LiteralNode(Token::fromText($text));
}


/** `[a, b: c]` */
function inline(): InlineArrayNode
{
	$comma = Token::fromText(',')->setTrailingTrivia([Trivia::fromText(' ')]);
	$colon = Token::fromText(':')->setTrailingTrivia([Trivia::fromText(' ')]);
	return new InlineArrayNode(Token::fromText('['), [
		new ArrayItemNode(null, null, null, literal('a'), $comma),
		new ArrayItemNode(null, literal('b'), $colon, literal('c')),
	], Token::fromText(']'));
}


test('a tree built by hand prints and knows its parents', function () {
	$array = inline();
	$document = new DocumentNode($array, Token::fromText(''));
	Assert::same('[a, b: c]', $document->print());
	Assert::same('[a, b: c]', (string) $array);
	Assert::same($document, $array->parent);
	Assert::same($array, $array->items[0]->parent);
	Assert::same($array->items[0], $array->items[0]->value?->parent);
	Assert::same(['0', 'b'], $array->getItemKeys());
	Assert::same($array->items[1], $array->findItem('b'));
	Assert::same(2, count($document->find(ArrayItemNode::class)));
	Assert::same($array->items[1], $document->findFirst(ArrayItemNode::class, fn(ArrayItemNode $item) => $item->key !== null));
	Assert::same($array->items[1], $array->items[0]->getNextSibling());
	Assert::same($array, $array->items[0]->value?->findAncestor(InlineArrayNode::class));
	Assert::false($document->isMultiLine());
	Assert::same(8, $array->items[1]->value?->getPosition()?->column);
});


test('arguments are given as lists', function () {
	$token = Token::fromText('x');
	Assert::exception(fn() => $token->setLeadingTrivia([0 => Trivia::fromText(' '), 2 => Trivia::fromText("\n")]), InvalidArgumentException::class, 'Trivia are given as a list.');
	Assert::same([], $token->leadingTrivia);
});


test('an instance of trivia stands in a list once, so that replaceTrivia() replaces the one meant', function () {
	$document = Neon::parse("a: 1\nb: 2\n");
	$token = $document->value?->items[1]->key?->getFirstToken();
	$eol = Trivia::fromText("\n");
	$token?->setLeadingTrivia([$eol, $eol]);
	Assert::notSame($token?->leadingTrivia[0], $token?->leadingTrivia[1]);
	$token?->replaceTrivia($eol, Trivia::fromText("\r\n"));
	Assert::same("a: 1\n\r\n\nb: 2\n", $document->print());
});


test('the guarded properties cannot be written from outside', function () {
	$array = inline();
	Assert::exception(fn() => $array->items[] = $array->items[0], Error::class, '%a%protected(set)%a%');
	Assert::exception(fn() => $array->items[0]->value = null, Error::class, '%a%protected(set)%a%');
	Assert::exception(fn() => serialize($array), LogicException::class);
	Assert::exception(fn() => serialize(Token::fromText('a')), LogicException::class);
});


test('clone is deep and detached', function () {
	$document = new DocumentNode(inline(), Token::fromText(''));
	$copy = clone $document;
	Assert::same($document->print(), $copy->print());
	Assert::null($copy->parent);
	$original = array_map(spl_object_id(...), $document->getTokens());
	Assert::same([], array_intersect($original, array_map(spl_object_id(...), $copy->getTokens())));
	Assert::same($copy, $copy->value?->parent);
	Assert::same($document, $document->value?->parent);

	$item = clone $document->value?->items[1];
	Assert::null($item?->parent);
	Assert::same('b: c', $item?->print());
});


test('the journal undoes the writes of a failed operation, a nested one to its savepoint', function () {
	$token = Token::fromText('a');
	$space = Trivia::fromText(' ');
	Assert::exception(fn() => Journal::atomic(function () use ($token, $space) {
		$token->setText('b');
		$token->setTrailingTrivia([$space]);
		Journal::atomic(fn() => $token->setText('c'));
		try {
			Journal::atomic(function () use ($token) {
				$token->setText('d');
				throw new RuntimeException('inner');
			});
		} catch (RuntimeException) {
		}
		Assert::same('c', $token->text);
		throw new TypeError('outer');
	}), TypeError::class, 'outer');
	Assert::same('a', $token->text);
	Assert::same([], $token->trailingTrivia);

	Journal::atomic(fn() => $token->setText('e'));
	Assert::same('e', $token->text);
});
