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
	function ($node) use (&$log) { $log[] = ['enter', $node::class]; },
	function ($node) use (&$log) { $log[] = ['leave', $node::class]; },
);

Assert::equal([
	['enter', Node\BlockArrayNode::class],
	['enter', Node\ArrayItemNode::class],
	['enter', Node\LiteralNode::class],
	['leave', Node\LiteralNode::class],
	['enter', Node\LiteralNode::class],
	['leave', Node\LiteralNode::class],
	['leave', Node\ArrayItemNode::class],
	['leave', Node\BlockArrayNode::class],
], $log);



$log = [];
$traverser->traverse(
	$node,
	function ($node) use (&$log) {
		$log[] = ['enter', $node::class];
		return $node instanceof Node\ArrayItemNode
			? Neon\Traverser::DontTraverseChildren
			: null;
	},
	function ($node) use (&$log) { $log[] = ['leave', $node::class]; },
);

Assert::equal([
	['enter', Node\BlockArrayNode::class],
	['enter', Node\ArrayItemNode::class],
	['leave', Node\ArrayItemNode::class],
	['leave', Node\BlockArrayNode::class],
], $log);



$log = [];
$traverser->traverse(
	$node,
	function ($node) use (&$log) {
		$log[] = ['enter', $node::class];
		return $node instanceof Node\ArrayItemNode ? Neon\Traverser::StopTraversal : null;
	},
	function ($node) use (&$log) { $log[] = ['enter', $node::class]; },
);

Assert::equal([
	['enter', Node\BlockArrayNode::class],
	['enter', Node\ArrayItemNode::class],
], $log);



$log = [];
$traverser->traverse(
	$node,
	null,
	function ($node) use (&$log) {
		$log[] = ['leave', $node::class];
		return $node instanceof Node\ArrayItemNode ? Neon\Traverser::StopTraversal : null;
	},
);

Assert::equal([
	['leave', Node\LiteralNode::class],
	['leave', Node\LiteralNode::class],
	['leave', Node\ArrayItemNode::class],
], $log);
