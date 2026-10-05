<?php declare(strict_types=1);

/**
 * PHPStan type tests.
 */

use Nette\Neon\{Neon, Node, Nodes, Traverser};
use function PHPStan\Testing\assertType;


function testNeonDecode(string $input): void
{
	assertType('mixed', Neon::decode($input));
}


function testNeonDecodeFile(string $file): void
{
	assertType('mixed', Neon::decodeFile($file));
}


function testNeonEncode(mixed $value): void
{
	assertType('string', Neon::encode($value));
}


function testNeonParse(string $input): void
{
	assertType(Nodes\DocumentNode::class, Neon::parse($input));
	assertType(Nodes\DocumentNode::class, Neon::parseFile($input));
}


function testDocumentNode(Nodes\DocumentNode $node): void
{
	assertType('mixed', $node->toValue());
	assertType('Nette\Neon\Nodes\ArrayItemNode|null', $node->findItem(['a']));
}


function testStringNode(Nodes\StringNode $node): void
{
	assertType('string', $node->toValue());
}


function testLiteralNode(Nodes\LiteralNode $node): void
{
	assertType('bool|DateTimeImmutable|float|int|string|null', $node->toValue());
}


function testEntityNode(Nodes\EntityNode $node): void
{
	assertType(Nette\Neon\Entity::class, $node->toValue());
}


function testEntityChainNode(Nodes\EntityChainNode $node): void
{
	assertType(Nette\Neon\Entity::class, $node->toValue());
}


function testBlockArrayNode(Nodes\BlockArrayNode $node): void
{
	assertType('array<mixed>', $node->toValue());
}


function testInlineArrayNode(Nodes\InlineArrayNode $node): void
{
	assertType('array<mixed>', $node->toValue());
}


function testArrayItemNode(Nodes\ArrayItemNode $node): void
{
	assertType('mixed', $node->getValue());
	assertType('string|null', $node->getKeyValue());
}


function testFind(Node $node): void
{
	assertType('list<Nette\Neon\Nodes\StringNode>', $node->find(Nodes\StringNode::class));
	assertType('Nette\Neon\Nodes\StringNode|null', $node->findFirst(Nodes\StringNode::class));
	assertType('Nette\Neon\Nodes\ArrayItemNode|null', $node->findAncestor(Nodes\ArrayItemNode::class));
}


function testTraverser(Node $node): void
{
	assertType('Nette\Neon\Node|null', Traverser::traverse($node));
}
