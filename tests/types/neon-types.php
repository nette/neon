<?php declare(strict_types=1);

/**
 * PHPStan type tests.
 */

use Nette\Neon\Neon;
use Nette\Neon\Nodes;
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
	assertType('string|null', $node->getKeyValue());
}
