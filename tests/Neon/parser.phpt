<?php declare(strict_types=1);

/**
 * Test: the parser, the dump and the printer.
 */

use Nette\Neon\{Exception, Neon, Printer};
use Nette\Neon\Nodes\{ArrayItemNode, BlockArrayNode, LiteralNode};
use Tester\Assert;
use Tests\Dumper;

require __DIR__ . '/../bootstrap.php';


test('the tree holds every token, the trivia stay with the tokens', function () {
	$input = "# head\na: 1 # one\nb:\n\t- x\n\n";
	$document = Neon::parse($input);
	Assert::same($input, $document->print());
	Assert::type(BlockArrayNode::class, $document->value);
	Assert::same(['a', 'b'], $document->value->getItemKeys());
	Assert::same('a: 1', Printer::printText($document->value->items[0]));
	Assert::same(
		'Literal "1" >[Whitespace " ", Comment "# one", LineEnding "\n"]',
		Dumper::dumpToken($document->value->items[0]->value->getFirstToken()),
	);
});


test('positions of all tokens in one walk', function () {
	$document = Neon::parse("a: [ž, b]\n");
	$positions = Printer::locateAll($document);
	$literals = $document->find(LiteralNode::class);
	Assert::same('1:5', $positions[$literals[1]->token]->line . ':' . $positions[$literals[1]->token]->column);
	Assert::same('1:8', $positions[$literals[2]->token]->line . ':' . $positions[$literals[2]->token]->column);
	Assert::equal($literals[2]->getPosition(), $positions[$literals[2]->token]);
});


test('errors carry the position, a line break included', function () {
	$e = Assert::exception(fn() => Neon::parse("a: [\n\n"), Exception::class, 'Unexpected end on line 3 at column 1');
	Assert::same('Unexpected end', $e->getDescription());

	Assert::exception(fn() => Neon::parse("a: b\n  c: d"), Exception::class, 'Bad indentation on line 2 at column 3');
	Assert::exception(fn() => Neon::parse("- a\nb"), Exception::class, 'Unexpected end on line 2 at column 2');
	Assert::exception(fn() => Neon::parse("a: 1\nfoo\n\n"), Exception::class, "Unexpected '<new line><new line>' on line 2 at column 4");
	Assert::exception(fn() => Neon::parse("[a\n\n\n:]"), Exception::class, "Unexpected ':' on line 4 at column 1");
	Assert::exception(fn() => Neon::parse('a: "\x"'), Exception::class, 'Invalid escaping sequence \x on line 1 at column 4');
	Assert::exception(fn() => Neon::parse("a: 1\na: 2"), Exception::class, "Duplicated key 'a' on line 2 at column 1");
	Assert::exception(fn() => Neon::parse('[a](b): c'), Exception::class, 'Unacceptable key on line 1 at column 1');
	Assert::exception(fn() => Neon::parse("'!!chain'(a)"), Exception::class, "Entity name '!!chain' is reserved on line 1 at column 1");
	Assert::exception(fn() => Neon::parse('[9223372036854775807: a, b]'), Exception::class, 'Implicit key overflow on line 1 at column 26');
});


test('500 levels of nesting are allowed, 501 are too deep', function () {
	Assert::noError(fn() => Neon::parse(str_repeat('[', 500) . str_repeat(']', 500)));
	Assert::exception(
		fn() => Neon::parse(str_repeat('[', 501) . str_repeat(']', 501)),
		Exception::class,
		'Too deep nesting on line 1 at column 501',
	);
	Assert::exception(fn() => Neon::parse(str_repeat('- ', 501) . 'a'), Exception::class, 'Too deep nesting on line 1 at column 1001');
});


test('a block on the line of a dash takes the indentation of its next line, in linear time', function () {
	$document = Neon::parse("- a: 1\n\tb: 2\n- c: 3\n  d: 4\n");
	Assert::same(['0', '1'], $document->value?->getItemKeys());
	Assert::count(2, $document->value->items[1]->value->items);

	$start = hrtime(true);
	Assert::exception(
		fn() => Neon::parse(str_repeat('- ', 300) . "a: 1\n" . str_repeat(' ', 3) . 'b: 2'),
		Exception::class,
		'Bad indentation on line 2 at column 4',
	);
	Assert::true(hrtime(true) - $start < 1e9);
	Assert::type(ArrayItemNode::class, Neon::parse(str_repeat('- ', 300) . 'a')->findFirst(ArrayItemNode::class));
});
