<?php declare(strict_types=1);

/**
 * Test: decode entity
 */

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

// the input must not be able to forge the chain marker
Assert::exception(
	fn() => Neon::decode('!!chain(a)'),
	Nette\Neon\Exception::class,
	"Entity name '!!chain' is reserved.",
);

Assert::exception(
	fn() => Neon::decode('"!!chain"(a)'),
	Nette\Neon\Exception::class,
	"Entity name '!!chain' is reserved.",
);
