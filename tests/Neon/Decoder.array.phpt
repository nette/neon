<?php

/**
 * Test: Nette\Neon\Neon::decode block hash and array.
 */

declare(strict_types=1);

use Nette\Neon\Neon;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


Assert::same(
	[
		'a' => [1, 2],
		'b' => 1,
	],
	Neon::decode(<<<'XX'
		a: {1, 2, }
		b: 1
		XX),
);


Assert::same(
	[
		'a' => 1,
		'b' => 2,
	],
	Neon::decode(<<<'XX'
		 a: 1
		 b: 2
		XX),
);


Assert::same(
	[
		'a' => 'x',
		'x',
	],
	Neon::decode(<<<'XX'
		a: x
		- x
		XX),
);


Assert::same(
	[
		'x',
		'a' => 'x',
	],
	Neon::decode(<<<'XX'
		- x
		a: x
		XX),
);


Assert::same(
	[
		'a' => [1, [2]],
		'b' => [3],
		'c' => null,
		4,
	],
	Neon::decode(<<<'XX'
		a:
		- 1
		-
		 - 2
		b:
		- 3
		c: null
		- 4
		XX),
);


Assert::same(
	[
		'x' => [
			'x',
			'a' => 'x',
		],
	],
	Neon::decode(<<<'XX'

		x:
			- x
			a: x

		XX),
);


Assert::same(
	[
		'x' => [
			'y' => [
				null,
			],
		],
		'a' => 'x',
	],
	Neon::decode(<<<'XX'
		x:
			y:
				-
		a: x
		XX),
);


Assert::same(
	[
		'x' => [
			'a' => 1,
			'b' => 2,
		],
	],
	Neon::decode(<<<'XX'
		x: {
			a: 1
		b: 2
		}
		XX),
);


Assert::same(
	[
		'one',
		'two',
	],
	Neon::decode(<<<'XX'
		{
			one
		two
		}
		XX),
);


Assert::same(
	[
		[
			'x' => 20,
			[
				'a' => 10,
				'b' => 10,
			],
		],
		['arr' => [10, 20]],
		'y',
	],
	Neon::decode(<<<'XX'
		- x: 20
		  - a: 10
		    b: 10
		- arr:
		  - 10
		  - 20
		- y
		XX),
);


Assert::same(
	[
		'root' => [['key1' => null, 'key3' => 123]],
	],
	Neon::decode(<<<XX
		root:
		\t- key1:
		\t  key3: 123
		\t
		XX),
);


Assert::same(
	[
		[
			'x' => ['a' => 10],
		],
	],
	Neon::decode(<<<'XX'
		- x:
		    a: 10

		XX),
);


Assert::same(
	[
		'x' => ['a' => 10],
		'y' => ['b' => 20],
	],
	Neon::decode(<<<XX
		x:
		\t a: 10
		y:
		 \tb: 20
		XX),
);


Assert::same(
	[
		['null' => 42],
		'null' => 42,
	],
	Neon::decode(<<<'XX'
		- {null= 42}
		null : 42
		XX),
);


Assert::same(
	[
		'x' => 'y',
	],
	Neon::decode(<<<XX

		x:
		\ty

		XX),
);


Assert::same(
	[
		0 => ['x' => 'y'],
	],
	Neon::decode(<<<XX
		-
		\tx:
		\t y
		XX),
);


Assert::same(
	[
		'x' => [1, 2, 3],
	],
	Neon::decode(<<<'XX'
		x:
			[1, 2, 3]
		XX),
);


Assert::same(
	[
		'a',
	],
	Neon::decode(<<<'XX'
		-
			a
		XX),
);


Assert::same(
	[
		'one' => null,
		'two' => null,
	],
	Neon::decode(<<<'XX'
		one:
		two:
		XX),
);


Assert::same(
	[null, null],
	Neon::decode(<<<'XX'
		-
		-
		XX),
);


Assert::equal(
	[
		new DateTimeImmutable('2016-06-03 00:00:00'),
		'2016-06-03' => 'b',
	],
	Neon::decode(<<<'XX'
		- 2016-06-03
		2016-06-03: b
		XX),
);


Assert::same(['a' => "a\u{A0}b"], Neon::decode("a: a\u{A0}b"));


Assert::same(
	[
		['a', ['b' => 1]],
		['c', ['d' => 1, 'e' => 1]],
	],
	Neon::decode(<<<'XX'
		  - - a
		    - b: 1
		  - - c
		    - d: 1
		      e: 1
		XX),
);
