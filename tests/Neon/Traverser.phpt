<?php

declare(strict_types=1);

use Nette\Neon;
use Nette\Neon\Node;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';

$visitor = function (Node $node) {
	if ($node instanceof Node\EntityNode) {
		foreach ($node->attributes as $i => $attr) {
			$attr->key ??= new Node\LiteralNode("key$i");
		}
	}
};

$decoder = new Neon\Decoder;
$node = $decoder->parseToNode(<<<'XX'

	a: foo(1, 2, 3)

	XX);

$traverser = new Neon\Traverser;
$node = $traverser->traverse($node, $visitor);
$value = $node->toValue();

Assert::equal([
	'a' => new Nette\Neon\Entity(
		'foo',
		['key0' => 1, 'key1' => 2, 'key2' => 3],
	),
], $value);
