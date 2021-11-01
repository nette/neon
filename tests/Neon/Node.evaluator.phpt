<?php

/** @phpVersion 7.2 */

declare(strict_types=1);

use Nette\Neon;
use Nette\Neon\Entity;
use Tester\Assert;
use Tracy\Dumper;


require __DIR__ . '/../bootstrap.php';


$input = [
	'map' => ['a' => 'b', 'c' => 'd'],
	'index' => ['a', 'b', 'c'],
	'mixed' => ['a', 'b', 4 => 'c',  'd'],
	'entity' => new Entity('ent', ['a', 'b']),
	'chain' => new Entity(Neon\Neon::CHAIN, [
		new Entity('first', ['a', 'b']),
		new Entity('second'),
	]),
	'multiline' => "hello\nworld",
	'date' => new DateTime('2016-06-03T19:00:00+02:00'),
];


class Test
{
	public function __construct($value)
	{
		$this->value = $value;
	}


	public function __toString()
	{
		return (string) $this->value;
	}
}


$encoder = new Neon\Encoder;
$node = $encoder->valueToNode($input);
$evaluator = function (Neon\Node $node) use (&$evaluator) {
	return new Test($node->toValue($evaluator));
};
$res = $evaluator($node);

Assert::matchFile(
	__DIR__ . '/fixtures/Node.evaluator.txt',
	Dumper::toText($res, [Dumper::DEPTH => 10, Dumper::HASH => false])
);
