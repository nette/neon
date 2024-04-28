<?php

/**
 * Test: decode entity
 */

declare(strict_types=1);

use Nette\Neon\Entity;
use Nette\Neon\Neon;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


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
