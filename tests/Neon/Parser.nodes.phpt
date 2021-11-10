<?php

/** @phpVersion 7.2 */

declare(strict_types=1);

use Nette\Neon;
use Nette\Neon\Node;
use Nette\Neon\Traverser;
use Tester\Assert;
use Tracy\Dumper;


require __DIR__ . '/../bootstrap.php';


$input = '
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
';


$lexer = new Neon\Lexer;
$parser = new Neon\Parser;
$stream = $lexer->tokenize($input);
$node = $parser->parse($stream);

Assert::matchFile(
	__DIR__ . '/fixtures/Parser.nodes.neon',
	$node->toString()
);

$traverser = new Traverser;
$traverser->traverse($node, function (Node $node) use ($stream) {
	$node->code = '';
	foreach (array_slice($stream->getTokens(), $node->startPos, $node->endPos - $node->startPos + 1) as $token) {
		$node->code .= $token->value;
	}
	unset($node->startPos, $node->endPos);
});

Assert::matchFile(
	__DIR__ . '/fixtures/Parser.nodes.txt',
	Dumper::toText($node, [Dumper::HASH => false])
);
