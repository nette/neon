<?php

declare(strict_types=1);

use Nette\Neon\Lexer;
use Nette\Neon\Parser;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

$lexer = new Lexer();
$fileContents = file_get_contents(__DIR__ . '/fixtures/nested-list.neon');
$tokens = $lexer->tokenize($fileContents);

$parser = new Parser();
$node = $parser->parse($tokens);

$reprintedNode = $node->toString();

Assert::matchFile(
	__DIR__ . '/fixtures/nested-list.neon',
	$reprintedNode
);
