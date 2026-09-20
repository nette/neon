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

	Assert::matchFile(
		__DIR__ . '/fixtures/parser.nodes.txt',
		Dumper::toText($node, [Dumper::HASH => false, Dumper::DEPTH => 20]),
	);
});


test('500 levels of nesting are allowed, 501 are too deep', function () {
	Assert::noError(fn() => Neon\Neon::decode(str_repeat('[', 500) . str_repeat(']', 500)));
	Assert::exception(
		fn() => Neon\Neon::decode(str_repeat('[', 501) . str_repeat(']', 501)),
		Neon\Exception::class,
		'Too deep nesting on line 1 at column 501',
	);
});
