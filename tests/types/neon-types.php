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
	assertType(Node::class, $decoder->parseToNode($input));
}








function testTraverser(Node $node): void
{
	$traverser = new Traverser;
	assertType(Node::class, $traverser->traverse($node));
}
