<?php declare(strict_types=1);

/**
 * Test: targeted cases of the editing API: the slots, the root, the refused operations and the low level.
 */

use Nette\Neon\{CommentPolicy, Entity, Neon, Token, Trivia};
use Nette\Neon\Nodes\{ArrayItemNode, BlockArrayNode, DocumentNode, EntityChainNode, EntityNode, InlineArrayNode, ValueNode};
use Tester\Assert;
use Tests\Guarantees;

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../helpers/guarantees.php';


function detached(string $text): ValueNode
{
	return Neon::parse($text)->value?->detach() ?? throw new LogicException;
}


function expectRefused(Closure $operation, string $message, DocumentNode ...$documents): void
{
	$unchanged = Guarantees::snapshot(...$documents);
	$e = Assert::exception($operation, Throwable::class);
	Assert::contains($message, $e->getMessage());
	$unchanged();
	array_map(Guarantees::checkTree(...), $documents);
}


function inline(DocumentNode $document): InlineArrayNode
{
	return $document->value instanceof InlineArrayNode ? $document->value : throw new LogicException;
}


test('a block cannot come into an inline array, through any door', function () {
	$doc = Neon::parse('[a]');
	$donor = Neon::parse("x: 1\n");
	$block = $donor->value;
	expectRefused(fn() => inline($doc)->items[0]->setValue($block), 'block', $doc, $donor);
	expectRefused(fn() => inline($doc)->addItem($block), 'block', $doc, $donor);
	expectRefused(fn() => inline($doc)->items[0]->value->replaceWith($block), 'block', $doc, $donor);
});


test('a node as the value is the editing operation: a block is reindented under its key', function () {
	$doc = Neon::parse("a: 1\nb: 2\n");
	$doc->findItem(['a'])->setValue(detached("x: 1\ny: 2\n"));
	Assert::same("a:\n\tx: 1\n\ty: 2\nb: 2\n", $doc->print());
	Guarantees::checkTree($doc);

	$doc->findItem(['b'])->setValue(detached("- p\n- q\n"));
	Assert::same("a:\n\tx: 1\n\ty: 2\nb:\n\t- p\n\t- q\n", $doc->print());
	Guarantees::checkTree($doc);

	$list = Neon::parse("- 1\n- 2\n");
	$list->addItem([], detached("k: v\nl: w\n"));
	Assert::same("- 1\n- 2\n-\n\tk: v\n\tl: w\n", $list->print());
	Guarantees::checkTree($list);
});


test('replaceWith() is decided by the slot', function () {
	$doc = Neon::parse("a: 1\n");
	$doc->findItem(['a'])->key->replaceWith(detached('b'));
	Assert::same("b: 1\n", $doc->print());

	$entity = Neon::parse("x: a(1)\n");
	$node = $entity->findItem(['x'])->value;
	assert($node instanceof EntityNode);
	$node->value->replaceWith(detached('b'));
	Assert::same("x: b(1)\n", $entity->print());
	expectRefused(fn() => $node->value->replaceWith(detached("'!!chain'")), 'reserved', $entity);
	$c = Neon::parse('c(2, 3)')->value;
	assert($c instanceof EntityNode);
	$node->attributes->replaceWith(clone $c->attributes);
	Assert::same("x: b(2, 3)\n", $entity->print());
	Guarantees::checkTree($entity);

	$chain = Neon::parse("a()b()\n");
	assert($chain->value instanceof EntityChainNode);
	$links = $chain->value->entities;
	$links[1]->replaceWith(detached('c(1)'));
	Assert::same("a()c(1)\n", $chain->print());
	expectRefused(fn() => $links[0]->replaceWith(detached('d')), 'EntityNode', $chain);
	expectRefused(fn() => $chain->value->entities[1]->value->replaceWith(detached("'q'")), 'literal', $chain);
});


test('a literal key gets a space after its colon, an inserted item its value on the line of the key', function () {
	$doc = Neon::parse('{"a":b}');
	inline($doc)->items[0]->key->replaceWith(detached('new'));
	Assert::same('{new: b}', $doc->print());
	Guarantees::checkTree($doc);

	$source = Neon::parse("b:\n  two words\nc: 1\n");
	$item = $source->findItem(['b']);
	$item->remove();
	$target = Neon::parse('E(a: 1)');
	assert($target->value instanceof EntityNode);
	$target->value->attributes->insert(0, $item);
	Assert::same('E(b: two words, a: 1)', $target->print());
	Guarantees::checkTree($target);
});


test('slots that cannot be empty refuse detach()', function () {
	$doc = Neon::parse("a: b(1)\n");
	expectRefused(fn() => $doc->findItem(['a'])->key->detach(), 'cannot be empty', $doc);
	expectRefused(fn() => $doc->findItem(['a'])->value->value->detach(), 'cannot be empty', $doc);
	$inline = Neon::parse('[a]');
	expectRefused(fn() => inline($inline)->items[0]->value->detach(), 'cannot be empty', $inline);
});


test('detaching the root leaves the preamble and the byte order mark', function () {
	$doc = Neon::parse("\u{FEFF}# license\n\n# about a\na: 1\n");
	$node = $doc->value->detach();
	Assert::same("\u{FEFF}# license\n\n", $doc->print());
	Assert::same("# about a\na: 1\n", $node->print());
	Guarantees::checkTree($doc);
});


test('a block at the root has the indentation of the old root block, or none', function () {
	$crlf = Neon::parse("42\r\n");
	$crlf->setValue([], ['a' => 1, 'b' => 2]);
	Assert::same("a: 1\r\nb: 2\r\n", $crlf->print());

	$indented = Neon::parse("  a: 1\n  b: 2\n");
	$indented->setValue([], ['x' => [1, 2], 'y' => 2]);
	Assert::same("  x:\n  \t- 1\n  \t- 2\n\n  y: 2\n", $indented->print());
	Guarantees::checkTree($indented);

	$scalar = Neon::parse("# c\na: 1\nb: 2\n");
	$scalar->setValue([], 'text');
	Assert::same("# c\ntext\n", $scalar->print());
});


test('an empty document', function () {
	foreach ([
		['', ['a' => 1, 'b' => 2], "a: 1\nb: 2"],
		['  ', 1, '1'],
		["\n\n", 1, "\n\n1\n"],
		["# a\n\n# b\n", [1], "# a\n\n# b\n- 1\n"],
	] as [$input, $value, $output]) {
		$doc = Neon::parse($input);
		$doc->setValue([], $value);
		Assert::same($output, $doc->print(), json_encode($input));
		Guarantees::checkTree($doc);
	}
});


test('nullable removes the root of either style', function () {
	foreach (["[a]\n", "- a\n"] as $input) {
		$doc = Neon::parse($input);
		$doc->nullable = true;
		$doc->removeItem([0]);
		Assert::null($doc->toValue());
		Guarantees::checkTree($doc);
	}
});


test('an implicit key out of range is refused', function () {
	$add = Neon::parse('{9223372036854775807: a}');
	expectRefused(fn() => inline($add)->addItem('b'), 'Implicit key overflow', $add);

	$rename = Neon::parse('{5: a, b}');
	expectRefused(fn() => $rename->setKey(['5'], '9223372036854775807'), 'Implicit key overflow', $rename);
});


test('a donor that already has a parent is refused', function () {
	$target = Neon::parse("a # origin\n");
	$donor = Neon::parse("b # donor\n");
	expectRefused(fn() => $target->value->replaceWith($donor->value), 'parent', $target, $donor);
	expectRefused(fn() => $target->setValue([], $donor->value), 'parent', $target, $donor);
});


test('a node into its own subtree is refused', function () {
	$doc = Neon::parse("a:\n\tb: 1\n");
	$block = $doc->value;
	assert($block instanceof BlockArrayNode && $block->items[0]->value instanceof BlockArrayNode);
	expectRefused(fn() => $block->items[0]->value->items[0]->setValue($block), 'parent', $doc);
});


test('arrays nested deeper than the parser reads are refused', function () {
	$doc = Neon::parse('a: ' . str_repeat('[', 498) . 'x' . str_repeat(']', 498));
	$path = ['a', ...array_fill(0, 497, 0)];
	$doc->setValue([...$path, 0], [1]); // level 500
	Assert::true(Neon::decode($doc->print()) === $doc->toValue());
	expectRefused(fn() => $doc->setValue([...$path, 0], [[1]]), 'Too deep nesting', $doc);
	expectRefused(fn() => $doc->findItem([...$path, 0])->setValue(detached('[[1]]')), 'Too deep nesting', $doc);
	expectRefused(fn() => $doc->findItem($path)->value->addItem(detached('[[1]]')), 'Too deep nesting', $doc);

	$chain = Neon::parse('[a()b()]');
	$link = detached('c(' . str_repeat('[', 499) . '1' . str_repeat(']', 499) . ')');
	expectRefused(fn() => $chain->value->items[0]->value->entities[1]->replaceWith($link), 'Too deep nesting', $chain);
});


test('an iterator of the value runs before the journal records, so what it does is not undone', function () {
	$doc = Neon::parse("a: 1\n");
	$other = Neon::parse("x: 1\n");
	$value = (function () use ($other) {
		$other->setValue(['x'], 2);
		yield 1;
		throw new RuntimeException('fails');
	})();
	Assert::exception(fn() => $doc->setValue(['a'], $value), RuntimeException::class, 'fails');
	Assert::same("a: 1\n", $doc->print());
	Assert::same("x: 2\n", $other->print());
});


test('the first item comes into an empty place as any other', function () {
	foreach (['', "a:\n"] as $input) {
		$doc = Neon::parse($input);
		$path = $input ? ['a'] : [];
		$doc->addItem($path, detached('[x]'), 'k');
		Assert::same($input ? "a:\n\tk: [x]\n" : 'k: [x]', $doc->print());
		Guarantees::checkTree($doc);

		$doc = Neon::parse($input);
		Assert::exception(fn() => $doc->addItem($path, 1, position: 1), OutOfRangeException::class, 'Position 1 is out of the array.');
		Assert::same($input, $doc->print());
	}
});


test('a value the encoder refuses changes nothing', function () {
	$doc = Neon::parse("a: 1\nb: [x]\n");
	expectRefused(fn() => $doc->setValue(['a'], INF), 'INF', $doc);
	expectRefused(fn() => $doc->setValue(['b'], [1, fn() => 0]), 'cannot be encoded', $doc);
	$twice = function () {
		yield 1 => 1;
		yield '1' => 2;
	};
	expectRefused(fn() => $doc->addItem(['b'], $twice()), 'twice', $doc);
	expectRefused(fn() => $doc->setValue(['a'], Neon::parse('x')), 'cannot be encoded', $doc);
	expectRefused(fn() => $doc->setValue(['a', 'b'], 1), 'The value at a is not an array', $doc);
	expectRefused(fn() => $doc->setValue(['x', 'b'], 1), "No item 'x' in the path x.b", $doc);
	expectRefused(fn() => $doc->addItem(['b'], 2, position: 5), 'out of the array', $doc);
	expectRefused(fn() => $doc->setValue([['x' => 1]], 2), 'segment', $doc);
});


test('removing keeps the comments of others: a block opened on the line of a dash', function () {
	$dropped = Neon::parse("- a: 1\n  # about b\n  b: 2\n");
	$dropped->removeItem([0, 'a']);
	Assert::same("-\n  # about b\n  b: 2\n", $dropped->print());
	Guarantees::checkTree($dropped);

	$moved = Neon::parse("- a: 1 # about a\n  b: 2\n");
	$moved->removeItem([0, 'a'], CommentPolicy::MoveToNextToken);
	Assert::same("-\n  # about a\n  b: 2\n", $moved->print());
	Guarantees::checkTree($moved);

	$plain = Neon::parse("- a: 1\n  b: 2\n");
	$plain->removeItem([0, 'a']);
	Assert::same("- b: 2\n", $plain->print());
});


test('removing keeps the comments of others: an inline array', function () {
	foreach ([
		['[a, b, c]', [1], '[a, c]'],
		['[a, b]', [1], '[a]'],
		['[a, b,]', [1], '[a,]'],
		["[\n\ta,\n\tb\n]", [1], "[\n\ta\n]"],
		["[\n\ta, b\n]", [0], "[\n\tb\n]"],
		["[x, a # c\n, b]", [1], "[x,\nb]"],
	] as [$input, $path, $output]) {
		$doc = Neon::parse($input);
		$doc->removeItem($path);
		Assert::same($output, $doc->print(), $input);
		Guarantees::checkTree($doc);
	}
});


test('writing an equal value is a no-op', function () {
	foreach ([
		["a: 9007199254740993\n", 9_007_199_254_740_993],
		["a: 1\n", 1],
		["a: 0x10\n", 16],
		["a: 1e999\n", INF],
		["a: -0.0\n", 0.0],
		["a: 9223372036854775808\n", '9223372036854775808'],
		["a: 2024-02-29\n", new DateTimeImmutable('2024-02-29')],
		["a: 2024-02-30\n", '2024-02-30'],
		["a: b(1, x: 2)\n", new Entity('b', [1, 'x' => 2])],
		["a: {x: 1, y: 2}\n", ['x' => 1, 'y' => 2]],
		["a: {x: 1, y: 2}\n", (object) ['x' => 1, 'y' => 2]],
		["a: {x: 1, y: 2}\n", new ArrayIterator(['x' => 1, 'y' => 2])],
		["a: []\n", []],
		["a: {}\n", []],
		["a: {}\n", new stdClass],
	] as [$input, $value]) {
		$doc = Neon::parse($input);
		$doc->setValue(['a'], $value);
		Assert::same($input, $doc->print(), $input);
	}
});


test('a changed value is written', function () {
	foreach ([
		["a: {x: 1, y: 2}\n", ['y' => 2, 'x' => 1], "a:\n\ty: 2\n\tx: 1\n"],
		["a: []\n", new stdClass, "a: {}\n"],
		["a: 1\n", 1.5, "a: 1.5\n"],
		["a: 1\n", 1.0, "a: 1.0\n"],
		["a: 0x0F\n", 255, "a: 0xFF\n"],
		["a: Yes\n", false, "a: No\n"],
		["a: 'x'\n", 'it\'s', "a: 'it''s'\n"],
		["a: \"x\"\n", "tab\t", "a: \"tab\\t\"\n"],
	] as [$input, $value, $output]) {
		$doc = Neon::parse($input);
		$doc->setValue(['a'], $value);
		Assert::same($output, $doc->print(), $input);
	}
});


test('a multiline string after a fragile one uses """, also from an iterator inside an object', function () {
	$doc = Neon::parse("old: '''\n'''\n");
	$doc->setValue(['new'], (object) ['nested' => (function () { yield "first\nsecond"; })()]);
	Assert::same("old: '''\n'''\nnew:\n\tnested:\n\t\t- \"\"\"\n\t\t\tfirst\n\t\t\tsecond\n\t\t\"\"\"\n", $doc->print());
	Assert::same(['old' => '', 'new' => ['nested' => ["first\nsecond"]]], Neon::decode($doc->print()));
});


test('trivia setters of an attached token check the place', function () {
	$doc = Neon::parse("a: 1\nb: 2\n");
	$key = $doc->findItem(['a'])->key->token;
	expectRefused(fn() => $key->setTrailingTrivia([Trivia::fromText('# hi')]), 'line ending', $doc);
	expectRefused(fn() => $doc->findItem(['b'])->key->token->setLeadingTrivia([Trivia::fromText("\u{FEFF}")]), 'byte order mark', $doc);
	expectRefused(fn() => $doc->findItem(['a'])->value->getFirstToken()->setLeadingTrivia([Trivia::fromText(' ')]), 'same line', $doc);
	$doc->findItem(['b'])
		->value
		->getFirstToken()
		->setTrailingTrivia([Trivia::fromText(' '), Trivia::fromText('# two'), Trivia::fromText("\n")]);
	Assert::same("a: 1\nb: 2 # two\n", $doc->print());
	Guarantees::checkTree($doc);

	$free = Token::fromText('x')->setTrailingTrivia([Trivia::fromText('# free')]);
	Assert::same('x# free', (string) $free);
});


test('a cloned item can be inserted elsewhere', function () {
	$doc = Neon::parse("a: b(1, [2]) # c\n");
	$item = clone $doc->findItem(['a']);
	Assert::null($item->parent);
	$target = Neon::parse("x: 1\n");
	$target->value->insert(1, $item);
	Assert::same("x: 1\na: b(1, [2]) # c\n", $target->print());
	Guarantees::checkTree($target);
	Assert::exception(
		fn() => $target->value->insert(0, $target->findItem(['x'])),
		InvalidArgumentException::class,
		'%a%already has a parent%a%',
	);

	$inline = Neon::parse("x: [k: 1, l: 2]\n");
	$target->value->insert(2, clone $inline->findItem(['x', 'k']));
	Assert::same("x: 1\na: b(1, [2]) # c\nk: 1\n", $target->print());
	Guarantees::checkTree($target);
});


test('a built token has no origin', function () {
	$doc = Neon::parse("a: 1\nb: 2\n");
	$doc->setValue(['a'], 'new');
	$doc->setValue(['c'], [1]);
	Assert::null($doc->findItem(['a'])->value->getFirstToken()->origin);
	Assert::true(array_all($doc->findItem(['c'])->getTokens(), fn(Token $token) => $token->origin === null));
	Assert::same(
		'2:4',
		$doc->findItem(['b'])->value->getFirstToken()->origin->line . ':' . $doc->findItem(['b'])->value->getFirstToken()->origin->column,
	);
});


test('an item moves to another indentation with its comments and multiline strings', function () {
	$document = Neon::parse(implode("\n", [
		'a:', "\tkeep: 1", "\t# about db", "\tdb:", "\t\thost: x", "\t\tnote: '''", "\t\t\tfirst", "\t\t\t  second", "\t\t'''", 'b:',
		'    x: 1', '',
	]));
	$db = $document->findItem(['a', 'db']);
	$db->remove();
	$target = $document->findItem(['b'])->value;
	$target->insert(1, $db);
	Assert::same(implode("\n", [
		'a:', "\tkeep: 1", 'b:', '    x: 1', '    # about db', '    db:', "    \thost: x", "    \tnote: '''", "\t\t\tfirst",
		"\t\t\t  second", "\t\t'''", '',
	]), $document->print());
	Guarantees::checkTree($document);
	Assert::same(['a' => ['keep' => 1], 'b' => ['x' => 1, 'db' => ['host' => 'x', 'note' => "first\n  second"]]], $document->toValue());

	$inline = Neon::parse("a: [x, y]\nb: [z]\n");
	$y = $inline->findItem(['a', 1]);
	$y->remove();
	$inline->findItem(['b'])->value->insert(0, $y);
	Assert::same("a: [x]\nb: [y, z]\n", $inline->print());
});


test('an item inserted into an inline array keeps its comments as lines above it, or is refused on one line', function () {
	$take = function (string $source): ArrayItemNode {
		$item = Neon::parse($source)->findItem(['x']);
		$item->remove();
		return $item;
	};
	$commented = "# belongs to x\nx: 1 # note\n";

	$document = Neon::parse("{\n\ta: 1, b: 2\n}\n");
	$document->value->insert(1, $take($commented));
	Assert::same("{\n\ta: 1,\n\t# belongs to x\n\t# note\n\tx: 1,\n\tb: 2\n}\n", $document->print());

	$document = Neon::parse("{\n\ta: 1\n}\n");
	$document->value->insert(0, $take("x: # after colon\n\t# above the value\n\t1\n"));
	Assert::same("{\n\t# after colon\n\t# above the value\n\tx: 1,\n\ta: 1\n}\n", $document->print());

	foreach (['{}', '{a: 1}'] as $text) {
		$document = Neon::parse($text);
		$item = $take($commented);
		$restore = Guarantees::snapshot($document, $item);
		Assert::exception(
			fn() => $document->value->insert(0, $item),
			Nette\Neon\Exception::class,
			'An item with comments cannot stand in an inline array on one line.',
		);
		$restore();
	}
	$document = Neon::parse('{a: 1}');
	$document->value->insert(1, $take("x: 1\n"));
	Assert::same('{a: 1, x: 1}', $document->print());
});


test('an item added before another in an inline array takes only its preamble', function () {
	$document = Neon::parse("[\n\t# header\n\n\t# about b\n\tb\n]");
	$document->value->addItem('a', position: 0);
	Assert::same("[\n\t# header\n\n\ta,\n\t# about b\n\tb\n]", $document->print());
	$document->value->items[0]->remove();
	Assert::same("[\n\t# header\n\n\t# about b\n\tb\n]", $document->print());
});


test('replaceWith() and detach() take the trivia at the edges', function () {
	$document = Neon::parse("a: 1 # one\nb: [x]\n");
	$one = $document->findItem(['a'])->value;
	$one->replaceWith(detached('2'));
	Assert::same("a: 2 # one\nb: [x]\n", $document->print());
	Assert::null($one->parent);

	$list = $document->findItem(['b'])->value->detach();
	Assert::same("a: 2 # one\nb:\n", $document->print());
	Assert::same("[x]\n", $list->print());
});
