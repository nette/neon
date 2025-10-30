<?php

declare(strict_types=1);

use Nette\Neon;
use Nette\Neon\Node;
use Nette\Neon\Traverser;
use Tester\Assert;
use Tracy\Dumper;


require __DIR__ . '/../bootstrap.php';


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
	__DIR__ . '/fixtures/Parser.nodes.neon',
	$node->toString(),
);

$traverser = new Traverser;
$traverser->traverse($node, function (Node $node) use ($stream) {
	@$node->code = ''; // dynamic property is deprecated
	foreach (array_slice($stream->tokens, $node->startTokenPos, $node->endTokenPos - $node->startTokenPos + 1) as $token) {
		$node->code .= $token->value;
	}

	unset($node->startTokenPos, $node->endTokenPos);
});

Assert::same(
	strtr(file_get_contents(__DIR__ . '/fixtures/Parser.nodes.txt'), ["\r\n" => "\n"]),
	Dumper::toText($node, [Dumper::HASH => false, Dumper::DEPTH => 20]),
);
