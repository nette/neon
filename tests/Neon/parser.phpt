<?php declare(strict_types=1);

/**
 * Test: the parser.
 */

use Nette\Neon;
use Nette\Neon\Node;
use Nette\Neon\Traverser;
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

	$lexer = new Neon\Lexer;
	$parser = new Neon\Parser;
	$stream = $lexer->tokenize($input);
	$node = $parser->parse($stream);

	Assert::matchFile(
		__DIR__ . '/fixtures/parser.nodes.neon',
		$node->toString(),
	);

	$traverser = new Traverser;
	$traverser->traverse($node, function (Node $node) use ($stream) {
		@$node->code = ''; // @ dynamic properties are deprecated
		foreach (array_slice($stream->getTokens(), $node->startTokenPos, $node->endTokenPos - $node->startTokenPos + 1) as $token) {
			$node->code .= $token->value;
		}

		unset($node->startTokenPos, $node->endTokenPos);
	});

	Assert::matchFile(
		__DIR__ . '/fixtures/parser.nodes.txt',
		Dumper::toText($node, [Dumper::HASH => false, Dumper::DEPTH => 20]),
	);
});
