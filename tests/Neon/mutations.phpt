<?php declare(strict_types=1);

/**
 * Test: the mutations corpus, checked against an independent model of the value, with the locality of each edit.
 */

use Nette\Neon\CommentPolicy;
use Nette\Neon\Entity;
use Nette\Neon\Neon;
use Nette\Neon\Nodes\ArrayItemNode;
use Nette\Neon\Nodes\ArrayNode;
use Nette\Neon\Nodes\DocumentNode;
use Nette\Neon\Nodes\LiteralNode;
use Nette\Neon\Nodes\StringNode;
use Tester\Assert;
use Tests\Corpus;
use Tests\Guarantees;
use Tests\Typed;

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../helpers/guarantees.php';


const OperationFields = [
	'set' => ['value', 'create'],
	'add' => ['value', 'key', 'position'],
	'remove' => ['comments', 'nullable', 'mergeBlankLines'],
	'setKey' => ['key'],
];


/**
 * Applies the operation; returns the item an `add` created.
 * @param  array<string, mixed>  $op
 */
function apply(DocumentNode $document, array $op): ?ArrayItemNode
{
	$known = OperationFields[$op['op']] ?? null;
	if (
		$known === null
		|| array_diff(array_keys($op), ['op', 'path', 'expectError', ...$known])
		|| !is_array($op['path'])
		|| !in_array($op['comments'] ?? 'drop', ['drop', 'move'], true)
	) {
		throw new LogicException('Invalid operation ' . json_encode($op));
	}
	$value = isset($op['value']) ? Typed::toEncodable($op['value']) : null;
	$comments = ($op['comments'] ?? null) === 'move' ? CommentPolicy::MoveToNextToken : CommentPolicy::Drop;
	switch ($op['op']) {
		case 'set':
			$document->setValue($op['path'], $value, $op['create'] ?? true);
			break;
		case 'add':
			return $document->addItem($op['path'], $value, $op['key'] ?? null, $op['position'] ?? null);
		case 'remove':
			$document->removeItem($op['path'], $comments, $op['nullable'] ?? null, $op['mergeBlankLines'] ?? false);
			break;
		case 'setKey':
			$document->setKey($op['path'], $op['key']);
			break;
	}
	return null;
}


/**
 * The lines an item spans, with the comment lines it owns above it, 0-based. The locality of an edit is checked
 * only in arrays whose keys are all written or all implicit, where no repair of the grammar can happen.
 * @return ?array{int, int}
 */
function itemLines(ArrayItemNode $item): ?array
{
	$parent = $item->parent;
	assert($parent instanceof ArrayNode);
	$keyed = array_filter($parent->items, fn($other) => $other->key !== null);
	if ($keyed && count($keyed) !== count($parent->items)) {
		return null;
	}
	$first = $item->getFirstToken();
	$lines = array_reverse(array_slice(explode('LineEnding', implode(' ', array_map(fn($t) => Nette\Neon\Trivia::Names[$t->kind], $first->leadingTrivia))), 0, -1));
	$owned = 0;
	while (isset($lines[$owned]) && str_contains($lines[$owned], 'Comment')) {
		$owned++;
	}
	$last = $item->getLastToken();
	return [$first->getPosition()->line - 1 - $owned, $last->getPosition()->line - 1 + substr_count($last->text, "\n")];
}


/** Only the lines of the item and one line on each side of it differ between the two texts. */
function checkLocality(string $withItem, string $without, array $lines, string $label): void
{
	$a = explode("\n", $withItem);
	$b = explode("\n", $without);
	$head = max(0, $lines[0] - 1);
	$tail = max(0, count($a) - $lines[1] - 2);
	Assert::same(array_slice($a, 0, $head), array_slice($b, 0, $head), "$label: the lines before");
	Assert::same($tail ? array_slice($a, -$tail) : [], $tail ? array_slice($b, -$tail) : [], "$label: the lines after");
}


// an independent model of the value: plain values edited directly


/** Changes the array (or the attributes of the entity) at the path by the function. */
function updateModel(mixed $model, array $path, Closure $fn): mixed
{
	if ($model instanceof Entity) {
		$model = clone $model;
		$model->attributes = (array) updateModel($model->attributes, $path, $fn);
		return $model;
	} elseif (!$path) {
		return $fn($model);
	}
	$key = modelKey($model, array_shift($path));
	$model[$key] = updateModel($model[$key] ?? null, $path, $fn);
	return $model;
}


function modelKey(mixed $container, mixed $segment): int|string
{
	return is_array($segment) ? array_keys($container)[$segment['item']] : $segment;
}


function applyToModel(mixed $model, array $op): mixed
{
	$value = isset($op['value']) ? Typed::toPhp($op['value']) : null;
	$path = $op['path'];
	$last = $path ? array_pop($path) : null;
	return match ($op['op']) {
		'set' => $last === null ? $value : updateModel($model, $path, function ($container) use ($last, $value) {
			$container ??= [];
			$container[modelKey($container, $last)] = $value;
			return $container;
		}),
		'add' => updateModel($model, $last === null ? [] : [...$path, $last], function ($container) use ($op, $value) {
			$container ??= [];
			$position = $op['position'] ?? count($container);
			if (array_is_list($container) && !isset($op['key'])) {
				array_splice($container, $position, 0, [$value]);
				return $container;
			}
			$key = $op['key'] ?? max([-1, ...array_filter(array_keys($container), is_int(...))]) + 1;
			return array_slice($container, 0, $position, true) + [$key => $value] + array_slice($container, $position, null, true);
		}),
		'remove' => updateModel($model, $path, function ($container) use ($op, $last) {
			$key = modelKey($container, $last);
			if (array_is_list($container)) {
				array_splice($container, (int) $key, 1);
			} else {
				unset($container[$key]);
			}
			return !$container && ($op['nullable'] ?? false) ? null : $container;
		}),
		'setKey' => updateModel($model, $path, function ($container) use ($op, $last) {
			$res = [];
			foreach ($container as $k => $v) {
				$res[(string) $k === (string) $last ? $op['key'] : $k] = $v;
			}
			return $res;
		}),
	};
}


/**
 * Where the token of a scalar at the path stands in the printed text, in code points.
 * @return ?array{int, int}
 */
function scalarLexeme(DocumentNode $document, array $path): ?array
{
	$node = $document->findItem($path)?->value;
	if (!$node instanceof LiteralNode && !$node instanceof StringNode) {
		return null;
	}
	$start = $node->token->getPosition()->offset;
	return [$start, $start + mb_strlen($node->token->text)];
}


foreach (Corpus::load('mutations') as $case) {
	if (!$case->runsHere()) {
		continue;
	}
	test($case->name, function () use ($case) {
		$document = Neon::parse((string) $case->read('.neon'));
		$model = $document->toValue();
		$expectedValue = $case->read('.expected.json'); // written down where a repair of the grammar makes the value differ from a plain edit
		$ops = json_decode((string) $case->read('.ops.json'), true, flags: JSON_THROW_ON_ERROR);

		foreach ($ops as $n => $op) {
			$label = 'operation ' . ($n + 1);
			$before = $document->print();
			$lexeme = $op['op'] === 'set' ? scalarLexeme($document, $op['path']) : null;
			$removed = $op['op'] === 'remove' && !isset($op['expectError']) ? $document->findItem($op['path']) : null;
			$removedLines = $removed ? itemLines($removed) : null;
			if (isset($op['expectError'])) {
				$unchanged = Guarantees::snapshot($document);
				$e = Assert::exception(fn() => apply($document, $op), Throwable::class);
				Assert::contains($op['expectError'], $e->getMessage(), $label);
				$unchanged();
			} else {
				$added = apply($document, $op);
				$model = applyToModel($model, $op);
				if ($added && ($addedLines = itemLines($added))) {
					checkLocality($document->print(), $before, $addedLines, "locality of $label");
				}
			}
			if ($removedLines) {
				checkLocality($before, $document->print(), $removedLines, "locality of $label");
			}
			Guarantees::checkTree($document);

			$stepValue = $n === count($ops) - 1 ? $expectedValue : $case->read('.expected.' . ($n + 1) . '.json');
			if ($expectedValue !== null && count($ops) > 1 && $n < count($ops) - 1 && $stepValue === null) {
				throw new LogicException('A case with .expected.json writes down the value after each operation: missing .expected.' . ($n + 1) . '.json');
			}
			$expected = $stepValue !== null ? Typed::toPhp(json_decode($stepValue, true, flags: JSON_THROW_ON_ERROR)) : $model;
			$value = Neon::decode($document->print());
			Assert::true(Typed::same($expected, $value), "the value after $label: " . var_export($value, true));

			if ($lexeme && isset($op['value']) && !in_array($op['value']['type'], ['list', 'map', 'null'], true)) { // locality of a scalar change
				$after = $document->print();
				Assert::same(mb_substr($before, 0, $lexeme[0]), mb_substr($after, 0, $lexeme[0]), "$label: the text before the lexeme");
				$tail = mb_strlen($before) - $lexeme[1];
				Assert::same(mb_substr($before, $lexeme[1]), $tail ? mb_substr($after, -$tail) : '', "$label: the text after the lexeme");
			}
			if (($step = $case->read('.expected.' . ($n + 1) . '.neon')) !== null) {
				Assert::same($step, $document->print(), $label);
			}
		}

		Assert::same($case->read('.expected.neon'), $document->print());
		if ($expectedValue !== null) {
			Assert::true(Typed::same(Typed::toPhp(json_decode($expectedValue, true, flags: JSON_THROW_ON_ERROR)), $document->toValue()), 'the value of the tree');
		}
	});
}
