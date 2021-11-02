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


$encoder = new Neon\Encoder;
$node = $encoder->valueToNode($input);

Assert::matchFile(
	__DIR__ . '/fixtures/Encoder.nodes.txt',
	Dumper::toText($node, [Dumper::HASH => false])
);
