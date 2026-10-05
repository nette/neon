<?php declare(strict_types=1);

/**
 * Test: the Traverser.
 */

use Nette\Neon\{Neon, Node, TraverseAction, Traverser};
use Nette\Neon\Nodes\{ArrayItemNode, LiteralNode};
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


function name(Node $node): string
{
	return $node instanceof LiteralNode ? $node->token->text : substr(strrchr($node::class, '\\') ?: '', 1);
}


test('order of enter and leave', function () {
	$document = Neon::parse("a: [b, c]\nd: e");
	$log = [];
	Traverser::traverse(
		$document,
		function (Node $node) use (&$log) { $log[] = '> ' . name($node); },
		function (Node $node) use (&$log) { $log[] = '< ' . name($node); },
	);
	Assert::same([
		'> DocumentNode', '> BlockArrayNode',
		'> ArrayItemNode', '> a', '< a', '> InlineArrayNode',
		'> ArrayItemNode', '> b', '< b', '< ArrayItemNode',
		'> ArrayItemNode', '> c', '< c', '< ArrayItemNode',
		'< InlineArrayNode', '< ArrayItemNode',
		'> ArrayItemNode', '> d', '< d', '> e', '< e', '< ArrayItemNode',
		'< BlockArrayNode', '< DocumentNode',
	], $log);
});


test('a value that is neither a node nor an action is refused', function () {
	$document = Neon::parse("a: [b, c]\nd: e");
	Assert::exception(
		fn() => Traverser::traverse($document, fn() => 1),
		LogicException::class,
		'A visitor of Nette\Neon\Nodes\DocumentNode returned int; %a%',
	);
	Assert::exception(
		fn() => Traverser::traverse($document, null, fn() => 'x'),
		LogicException::class,
		'A visitor of Nette\Neon\Nodes\LiteralNode returned string; %a%',
	);
	Assert::same("a: [b, c]\nd: e", $document->print());
});


test('skipping children and stopping', function () {
	$document = Neon::parse("a: [b, c]\nd: e");
	$log = [];
	Traverser::traverse($document, function (Node $node) use (&$log) {
		$log[] = name($node);
		if ($node instanceof ArrayItemNode && $node->getKeyValue() === 'a') {
			return TraverseAction::SkipChildren;
		} elseif ($node instanceof LiteralNode && $node->token->text === 'd') {
			return TraverseAction::Stop;
		}
		return null;
	});
	Assert::same(['DocumentNode', 'BlockArrayNode', 'ArrayItemNode', 'ArrayItemNode', 'd'], $log);
});


test('removing and replacing', function () {
	$document = Neon::parse("debug: yes # on\nlist: [x, y, z]\n");
	$res = Traverser::traverse($document, function (Node $node) {
		if ($node instanceof ArrayItemNode && $node->getValue() === 'y') {
			return TraverseAction::Remove;
		} elseif ($node instanceof LiteralNode && $node->token->text === 'yes') {
			return Neon::parse('no')->value?->detach();
		}
		return null;
	});
	Assert::same($document, $res);
	Assert::same("debug: no # on\nlist: [x, z]\n", $document->print());
	Assert::exception(
		fn() => Traverser::traverse($document, fn() => TraverseAction::Remove),
		LogicException::class,
		'Only an item can be removed%a%',
	);
	Assert::exception(
		fn() => Traverser::traverse($document->value->items[0], fn() => Neon::parse('a')->value?->detach()),
		LogicException::class,
		'Only a value node can be replaced%a%',
	);
});


test('stopping enters and leaves nothing after', function () {
	$document = Neon::parse("a:\n\tb: 1\nc: 2");
	$log = [];
	Traverser::traverse(
		$document,
		function (Node $node) use (&$log) {
			$log[] = '> ' . name($node);
			return $node instanceof LiteralNode && $node->token->text === '1' ? TraverseAction::Stop : null;
		},
		function (Node $node) use (&$log) { $log[] = '< ' . name($node); },
	);
	Assert::same([
		'> DocumentNode', '> BlockArrayNode', '> ArrayItemNode', '> a', '< a', '> BlockArrayNode', '> ArrayItemNode', '> b', '< b', '> 1',
	], $log);
});


test('a sibling taken out by the visitor is skipped', function () {
	$document = Neon::parse("a: 1\nb: 2\nc: 3\n");
	$log = [];
	Traverser::traverse($document, function (Node $node) use (&$log) {
		if ($node instanceof ArrayItemNode) {
			$log[] = $node->getKeyValue();
			if ($node->getKeyValue() === 'a') {
				$node->getNextSibling()->remove();
			} elseif ($node->getKeyValue() === 'b') {
				return TraverseAction::Remove;
			}
		}
		return null;
	});
	Assert::same(['a', 'c'], $log);
	Assert::same("a: 1\nc: 3\n", $document->print());
});


test('a node the visitor takes out itself is neither walked into nor left', function () {
	$document = Neon::parse("a: [x, y]\nb: [z]\n");
	$log = [];
	$res = Traverser::traverse(
		$document->value,
		function (Node $node) use (&$log) {
			$log[] = '> ' . name($node);
			if ($node instanceof ArrayItemNode && $node->getKeyValue() === 'a') {
				$node->remove();
			}
		},
		function (Node $node) use (&$log) { $log[] = '< ' . name($node); },
	);
	Assert::same($document->value, $res);
	Assert::same([
		'> BlockArrayNode', '> ArrayItemNode', '> ArrayItemNode', '> b', '< b', '> InlineArrayNode', '> ArrayItemNode', '> z', '< z',
		'< ArrayItemNode', '< InlineArrayNode', '< ArrayItemNode', '< BlockArrayNode',
	], $log);
	Assert::same("b: [z]\n", $document->print());
});


test('a node taken out while its children are walked is not left', function () {
	$document = Neon::parse("a: [x, y]\nb: 1\n");
	$log = [];
	Traverser::traverse(
		$document,
		function (Node $node) use (&$log) {
			$log[] = '> ' . name($node);
			if ($node instanceof LiteralNode && $node->token->text === 'x') {
				$node->findAncestor(ArrayItemNode::class)->findAncestor(ArrayItemNode::class)->remove();
			}
		},
		function (Node $node) use (&$log) { $log[] = '< ' . name($node); },
	);
	Assert::same([
		'> DocumentNode', '> BlockArrayNode', '> ArrayItemNode', '> a', '< a', '> InlineArrayNode', '> ArrayItemNode', '> x',
		'> ArrayItemNode', '> b', '< b', '> 1', '< 1', '< ArrayItemNode', '< BlockArrayNode', '< DocumentNode',
	], $log);
	Assert::same("b: 1\n", $document->print());
});
