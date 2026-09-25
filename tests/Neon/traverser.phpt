<?php declare(strict_types=1);

/**
 * Test: the Traverser.
 */

use Nette\Neon\{Neon, Node, Traverser};
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
	(new Traverser)->traverse(
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


test('skipping children and stopping', function () {
	$document = Neon::parse("a: [b, c]\nd: e");
	$log = [];
	(new Traverser)->traverse($document, function (Node $node) use (&$log) {
		$log[] = name($node);
		if ($node instanceof ArrayItemNode && $node->getKeyValue() === 'a') {
			return Traverser::DontTraverseChildren;
		} elseif ($node instanceof LiteralNode && $node->token->text === 'd') {
			return Traverser::StopTraversal;
		}
		return null;
	});
	Assert::same(['DocumentNode', 'BlockArrayNode', 'ArrayItemNode', 'ArrayItemNode', 'd'], $log);

	$log = [];
	(new Traverser)->traverse($document, null, function (Node $node) use (&$log) {
		$log[] = name($node);
		return $node instanceof ArrayItemNode ? Traverser::StopTraversal : null;
	});
	Assert::same(['a', 'b', 'ArrayItemNode'], $log);
});
