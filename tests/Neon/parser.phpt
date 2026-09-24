<?php declare(strict_types=1);

/**
 * Test: the parser.
 */

use Nette\Neon;
use Nette\Neon\{Node, Traverser};
use Tester\Assert;
use Tracy\Dumper;

require __DIR__ . '/../bootstrap.php';


test('nodes know the tokens they were parsed from', function () {
	$input = <<<'XX'

		# hello
		first: # first comment
			# another comment
			- a  # a comment
		next:
			- [k,
				l, m:
			n]
		second:
			sub:
				a: 1
				b: 2
		third:
			- entity(a: 1)
			- entity(a: 1)foo()bar
		- a: 1
		  b: 2
		- - c
		dash subblock:
		- a
		- b
		text: """
		     one
		     two
		"""
		# world

		XX;

	$parser = new Neon\Parser;
	$stream = new Neon\TokenStream(new Neon\Lexer()->tokenize($input));
	$node = $parser->parse($stream);

	$traverser = new Traverser;
	$traverser->traverse($node, function (Node $node) use ($stream) {
		$tokens = array_slice($stream->tokens, $node->startTokenPos, $node->endTokenPos - $node->startTokenPos + 1);
		$code = implode('', $tokens);
		$code = substr($code, strlen(implode('', array_column($tokens[0]->leadingTrivia, 'text'))));
		$code = substr($code, 0, strlen($code) - strlen(implode('', array_column(end($tokens)->trailingTrivia, 'text'))));
		@$node->code = $code; // @ dynamic properties are deprecated

		unset($node->startTokenPos, $node->endTokenPos);
	});

	Assert::same(
		rtrim(str_replace("\r\n", "\n", (string) file_get_contents(__DIR__ . '/fixtures/parser.nodes.txt'))),
		rtrim(Dumper::toText($node, [Dumper::HASH => false, Dumper::DEPTH => 20])),
	);
});


test('500 levels of nesting are allowed, 501 are too deep', function () {
	Assert::noError(fn() => Neon\Neon::decode(str_repeat('[', 500) . str_repeat(']', 500)));
	Assert::exception(
		fn() => Neon\Neon::decode(str_repeat('[', 501) . str_repeat(']', 501)),
		Neon\Exception::class,
		'Too deep nesting on line 1 at column 501',
	);
	Assert::exception(
		fn() => Neon\Neon::decode(str_repeat('- ', 501) . 'a'),
		Neon\Exception::class,
		'Too deep nesting on line 1 at column 1001',
	);
});


test('a block on the line of a dash takes the indentation of its next line, in linear time', function () {
	Assert::same([['a' => 1, 'b' => 2], ['c' => 3, 'd' => 4]], Neon\Neon::decode("- a: 1\n\tb: 2\n- c: 3\n  d: 4\n"));

	$start = hrtime(true);
	Assert::exception(
		fn() => Neon\Neon::decode(str_repeat('- ', 300) . "a: 1\n" . str_repeat(' ', 3) . 'b: 2'),
		Neon\Exception::class,
		'Bad indentation on line 2 at column 4',
	);
	Assert::true(hrtime(true) - $start < 1e9);
});
