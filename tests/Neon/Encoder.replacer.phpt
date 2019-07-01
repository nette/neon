<?php
/**
 * Test: Nette\Neon\Neon::encode & replacer.
 */

declare(strict_types=1);

use Nette\Neon\Entity;
use Nette\Neon\Neon;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


$res = null;
$data = ['foo' => 1, 'bar' => [2, 3], new Entity('ent', ['a'])];

Assert::same(
	'{foo: 2, bar: [3, 4], 1: ent(a)}',
	Neon::encode(
		$data,
		[Neon::REPLACER => function (&$key, &$val, $depth) use (&$res) {
			$res[] = func_get_args();
			$key = is_int($key) ? $key + 1 : $key;
			$val = is_int($val) ? $val + 1 : $val;
		}]
	)
);

Assert::same(
	[
		[null, $data, 0],
		['foo', 1, 1],
		['bar', [2, 3], 1],
		[0, 2, 2],
		[1, 3, 2],
		[0, $data[0], 1],
		[0, 'a', 3],
	],
	$res
);
