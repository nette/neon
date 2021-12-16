<?php

declare(strict_types=1);

use Nette\Neon;
use Nette\Neon\Node;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';

$traverser = new Neon\Traverser;
$decoder = new Neon\Decoder;
$node = $decoder->parseToNode('
a: 1
');


$log = [];
$traverser->traverse(
	$node,
	function ($node) use (&$log) { $log[] = ['enter', get_class($node)]; }
);

Assert::equal([
	['enter', Node\BlockArrayNode::class],
	['enter', Node\ArrayItemNode::class],
	['enter', Node\LiteralNode::class],
	['enter', Node\LiteralNode::class],
], $log);



$log = [];
$traverser->traverse(
	$node,
	function ($node) use (&$log) {
		$log[] = ['enter', get_class($node)];
		return $node instanceof Node\ArrayItemNode
			? Neon\Traverser::DontTraverseChildren
			: null;
	}
);

Assert::equal([
	['enter', Node\BlockArrayNode::class],
	['enter', Node\ArrayItemNode::class],
], $log);



$log = [];
$traverser->traverse(
	$node,
	function ($node) use (&$log) {
		$log[] = ['enter', get_class($node)];
		return $node instanceof Node\ArrayItemNode ? Neon\Traverser::StopTraversal : null;
	}
);

Assert::equal([
	['enter', Node\BlockArrayNode::class],
	['enter', Node\ArrayItemNode::class],
], $log);
