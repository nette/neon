<?php

/**
 * Test: Nette\Neon\Neon::decode inline hash and array.
 */

declare(strict_types=1);

use Nette\Neon\Entity;
use Nette\Neon\Neon;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


Assert::same([
	'foo' => 'bar',
], Neon::decode('{"foo":"bar"}'));


Assert::same([
	true, 'tRuE', true, false, false, true, true, false, false, null, null,
], Neon::decode('[true, tRuE, TRUE, false, FALSE, yes, YES, no, NO, null, NULL,]'));


Assert::same([
	'false' => false,
	'on' => true,
	-5 => 1,
	'5.3' => 1,
], Neon::decode('{false: no, "on": true, -5: 1, 5.3: 1}'));


Assert::same([
	0 => 'a',
	1 => 'b',
	2 => [
		'c' => 'd',
	],
	'e' => 'f',
	'g' => null,
	'h' => null,
], Neon::decode('{a, b, {c: d}, e: f, g:,h:}'));


Assert::same([
	'a',
	'b',
	'c' => 1,
	'd' => 1,
	'e' => 1,
	'f' => null,
], Neon::decode("{a,\nb\nc: 1,\nd: 1,\n\ne: 1\nf:\n}"));


Assert::type(Nette\Neon\Entity::class, Neon::decode('@item(a, b)'));


Assert::equal(
	new Entity('@item', ['a', 'b']),
	Neon::decode('@item(a, b)'),
);


Assert::equal(
	new Entity('@item<item>', ['a', 'b']),
	Neon::decode('@item<item>(a, b)'),
);


Assert::equal(
	new Entity('item', ['a', 'b']),
	Neon::decode('item (a, b)'),
);


Assert::equal(
	new Entity([], []),
	Neon::decode('[]()'),
);


Assert::equal(
	new Entity(Neon::Chain, [
		new Entity('first', ['a', 'b']),
		new Entity('second'),
	]),
	Neon::decode('first(a, b)second'),
);


Assert::equal(
	new Entity(Neon::Chain, [
		new Entity('first', ['a', 'b']),
		new Entity('second', [1, 2]),
	]),
	Neon::decode('first(a, b)second(1, 2)'),
);

Assert::equal(
	new Entity(Neon::Chain, [
		new Entity(1, []),
		new Entity(2, []),
	]),
	Neon::decode('1() 2()'),
);

// JSON compatibility
Assert::same(['a' => true], Neon::decode('{"a":true}'));
