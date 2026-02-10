<?php declare(strict_types=1);

/**
 * PHPStan type tests.
 */

use Nette\Neon\Decoder;
use Nette\Neon\Neon;
use Nette\Neon\Node;
use Nette\Neon\Traverser;
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


function testDecoder(string $input): void
{
	$decoder = new Decoder;
	assertType('mixed', $decoder->decode($input));
	assertType('Nette\Neon\Node', $decoder->parseToNode($input));
}


function testStringNode(Node\StringNode $node): void
{
	assertType('string', $node->toValue());
}


function testLiteralNode(Node\LiteralNode $node): void
{
	assertType('bool|DateTimeInterface|float|int|string|null', $node->toValue());
}


function testLiteralNodeParse(string $value): void
{
	assertType('bool|DateTimeImmutable|float|int|string|null', Node\LiteralNode::parse($value));
	assertType('bool|DateTimeImmutable|float|int|string|null', Node\LiteralNode::parse($value, false));
	assertType('float|int|string', Node\LiteralNode::parse($value, true));
}


function testLiteralNodeBaseConvert(string $number, int $base): void
{
	assertType('int|string', Node\LiteralNode::baseConvert($number, $base));
}


function testEntityNode(Node\EntityNode $node): void
{
	assertType('Nette\Neon\Entity', $node->toValue());
}


function testEntityChainNode(Node\EntityChainNode $node): void
{
	assertType('Nette\Neon\Entity', $node->toValue());
}


function testBlockArrayNode(Node\BlockArrayNode $node): void
{
	assertType('array<mixed>', $node->toValue());
}


function testInlineArrayNode(Node\InlineArrayNode $node): void
{
	assertType('array<mixed>', $node->toValue());
}


function testTraverser(Node $node): void
{
	$traverser = new Traverser;
	assertType('Nette\Neon\Node', $traverser->traverse($node));
}
