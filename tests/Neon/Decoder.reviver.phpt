<?php

/**
 * Test: Nette\Neon\Neon::decode reviver.
 */

declare(strict_types=1);

use Nette\Neon\Entity;
use Nette\Neon\Neon;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


Assert::equal(
	[
		'foo' => 3,
		'bar' => [1 => 5, 3 => 7],
		1 => new Entity('ent', [1 => 'a']),
	],
	Neon::decode(
		'{foo: 2, bar: [4, 6], ent(a)}',
		[Neon::REVIVER => function (&$key, &$val) use (&$res) {
			$res[] = func_get_args();
			$key = is_int($key) ? $key + 1 : $key;
			$val = is_int($val) ? $val + 1 : $val;
		}]
	)
);

Assert::equal(
	[
		['foo', 2],
		[0, 4],
		[2, 6],
		['bar', [1 => 5, 3 => 7]],
		[0, 'a'],
		[0, new Entity('ent', [1 => 'a'])],
	],
	$res
);
