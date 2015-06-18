<?php

/**
 * Test: Nette\Neon\Neon::decode block hash and array.
 */

use Nette\Neon\Neon;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


Assert::same([
	'a' => [1, 2],
	'b' => 1,
], Neon::decode('
a: {1, 2, }
b: 1'));


Assert::same([
	'a' => 1,
	'b' => 2,
], Neon::decode(
' a: 1
 b: 2'));


Assert::same([
	'a' => 'x',
	'x',
], Neon::decode('
a: x
- x'));


Assert::same([
	'x',
	'a' => 'x',
], Neon::decode('
- x
a: x
'));


Assert::same([
	'a' => [1, [2]],
	'b' => [3],
	'c' => NULL,
	4,
], Neon::decode('
a:
- 1
-
 - 2
b:
- 3
c: null
- 4'));


Assert::same([
	'x' => [
		'x',
		'a' => 'x',
	],
], Neon::decode('
x:
	- x
	a: x
'));


Assert::same([
	'x' => [
		'y' => [
			NULL,
		],
	],
	'a' => 'x',
], Neon::decode(
'x:
	y:
		-
a: x
'));


Assert::same([
	'x' => [
		'a' => 1,
		'b' => 2,
	],
], Neon::decode('
x: {
	a: 1
b: 2
}
'));


Assert::same([
	'one',
	'two',
], Neon::decode('
{
	one
two
}
'));


Assert::same([
	[
		'x' => 20,
		[
			'a' => 10,
			'b' => 10,
		],
	],
	['arr' => [10, 20]],
	'y',
], Neon::decode('
- x: 20
  - a: 10
    b: 10
- arr:
  - 10
  - 20
- y
'));


Assert::same([
	'root' => [['key1' => NULL, 'key3' => 123]],
], Neon::decode("
root:
\t- key1:
\t  key3: 123
\t"));


Assert::same([
	[
		'x' => ['a' => 10],
	],
], Neon::decode('
- x:
    a: 10
'));


Assert::same([
	'x' => ['a' => 10],
	'y' => ['b' => 20],
], Neon::decode("
x:
\t a: 10
y:
 \tb: 20
"));


Assert::same([
	['null' => 42],
	'null' => 42,
], Neon::decode('
- {null= 42}
null : 42
'));


Assert::same([
	'x' => 'y',
], Neon::decode("
x:
\ty
"));


Assert::same([
	0 => ['x' => 'y'],
], Neon::decode("
-
\tx:
\t y
"));


Assert::same([
	'x' => [1, 2, 3],
], Neon::decode('
x:
    [1, 2, 3]
'));


Assert::same([
	'a'
], Neon::decode('
-
    a
'));


Assert::same([
	'one' => NULL,
	'two' => NULL,
], Neon::decode('
one:
two:
'));
