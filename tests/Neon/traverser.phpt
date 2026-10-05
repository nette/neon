<?php declare(strict_types=1);

/**
 * Test: the Traverser.
 */

use Nette\Neon;
use Nette\Neon\Node;
use Nette\Neon\Traverser;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


function parse(string $input): Node
{
	return (new Neon\Decoder)->parseToNode($input);
}


test('order of enter and leave', function () {
	$node = parse('a: 1');
	$log = [];
	(new Traverser)->traverse(
		$node,
		function (Node $node) use (&$log) { $log[] = ['enter', $node::class]; },
		function (Node $node) use (&$log) { $log[] = ['leave', $node::class]; },
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
});


test('skipping children and stopping', function () {
	$node = parse('a: 1');
	$log = [];
	(new Traverser)->traverse(
		$node,
		function (Node $node) use (&$log) {
			$log[] = ['enter', $node::class];
			return $node instanceof Node\ArrayItemNode ? Traverser::DontTraverseChildren : null;
		},
		function (Node $node) use (&$log) { $log[] = ['leave', $node::class]; },
	);
	Assert::equal([
		['enter', Node\BlockArrayNode::class],
		['enter', Node\ArrayItemNode::class],
		['leave', Node\ArrayItemNode::class],
		['leave', Node\BlockArrayNode::class],
	], $log);

	$log = [];
	(new Traverser)->traverse(
		$node,
		function (Node $node) use (&$log) {
			$log[] = ['enter', $node::class];
			return $node instanceof Node\ArrayItemNode ? Traverser::StopTraversal : null;
		},
		function (Node $node) use (&$log) { $log[] = ['leave', $node::class]; },
	);
	Assert::equal([
		['enter', Node\BlockArrayNode::class],
		['enter', Node\ArrayItemNode::class],
	], $log);

	$log = [];
	(new Traverser)->traverse(
		$node,
		null,
		function (Node $node) use (&$log) {
			$log[] = ['leave', $node::class];
			return $node instanceof Node\ArrayItemNode ? Traverser::StopTraversal : null;
		},
	);
	Assert::equal([
		['leave', Node\LiteralNode::class],
		['leave', Node\LiteralNode::class],
		['leave', Node\ArrayItemNode::class],
	], $log);
});


test('a visitor changes the nodes in place', function () {
	$node = parse('a: foo(1, 2, 3)');
	$node = (new Traverser)->traverse($node, function (Node $node) {
		if ($node instanceof Node\EntityNode) {
			foreach ($node->attributes as $i => $attr) {
				$attr->key ??= new Node\LiteralNode("key$i");
			}
		}
	});
	Assert::equal([
		'a' => new Neon\Entity('foo', ['key0' => 1, 'key1' => 2, 'key2' => 3]),
	], $node->toValue());
});


test('a replacement returned by the visitor takes the place of the node', function () {
	$node = parse('a: foo(1, 2, 3)');
	$newNode = (new Traverser)->traverse($node, fn(Node $node) => clone $node);

	Assert::equal($node, $newNode);
	Assert::notSame($node, $newNode);
	Assert::notSame($node->items[0], $newNode->items[0]);
	Assert::notSame($node->items[0]->key, $newNode->items[0]->key);
	Assert::notSame($node->items[0]->value, $newNode->items[0]->value);
	Assert::notSame($node->items[0]->value->value, $newNode->items[0]->value->value);
	Assert::notSame($node->items[0]->value->attributes[0], $newNode->items[0]->value->attributes[0]);
});
