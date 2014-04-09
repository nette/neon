<?php

/**
 * Test: Nette\Neon\Neon::decode block hash and array.
 */

use Nette\Neon\Neon,
	Tester\Assert;


require __DIR__ . '/../bootstrap.php';


Assert::same( array(
	'a' => array(1, 2),
	'b' => 1,
), Neon::decode('
a: {1, 2, }
b: 1') );


Assert::same( array(
	'a' => 1,
	'b' => 2,
), Neon::decode(
' a: 1
 b: 2') );


Assert::same( array(
	'a' => 'x',
	'x',
), Neon::decode('
a: x
- x') );


Assert::same( array(
	'x',
	'a' => 'x',
), Neon::decode('
- x
a: x
') );


Assert::same( array(
	'a' => array(1, array(2)),
	'b' => array(3),
	'c' => NULL,
	4,
), Neon::decode('
a:
- 1
-
 - 2
b:
- 3
c: null
- 4') );


Assert::same( array(
	'x' => array(
		'x',
		'a' => 'x',
	),
), Neon::decode('
x:
	- x
	a: x
') );


Assert::same( array(
	'x' => array(
		'y' => array(
			NULL,
		),
	),
	'a' => 'x',
), Neon::decode(
'x:
	y:
		-
a: x
') );


Assert::same( array(
	'x' => array(
		'a' => 1,
		'b' => 2,
	),
), Neon::decode('
x: {
	a: 1
b: 2
}
') );


Assert::same( array(
	'one',
	'two',
), Neon::decode('
{
	one
two
}
') );


Assert::same( array(
	array(
		'x' => 20,
		array(
			'a' => 10,
			'b' => 10,
		),
	),
	array('arr' => array(10, 20)),
	'y',
), Neon::decode('
- x: 20
  - a: 10
    b: 10
- arr:
  - 10
  - 20
- y
') );


Assert::same( array(
	'root' => array(array('key1' => NULL, 'key3' => 123))
), Neon::decode("
root:
\t- key1:
\t  key3: 123
\t") );


Assert::same( array(
	array(
		'x' => array('a' => 10),
	),
), Neon::decode('
- x:
    a: 10
') );


Assert::same( array(
	'x' => array('a' => 10),
	'y' => array('b' => 20),
), Neon::decode("
x:
\t a: 10
y:
 \tb: 20
") );


Assert::same( array(
	array('null' => 42),
	'null' => 42,
), Neon::decode('
- {null= 42}
null : 42
') );


Assert::same( array(
	'x' => 'y'
), Neon::decode("
x:
\ty
") );


Assert::same(array(
	0 => array('x' => 'y'),
), Neon::decode("
-
\tx:
\t y
"));


Assert::same(array(
	'x' => array(1, 2, 3),
), Neon::decode("
x:
    [1, 2, 3]
"));


Assert::same(array(
	'a'
), Neon::decode("
-
    a
"));


Assert::same( array(
	'one' => NULL,
	'two' => NULL,
), Neon::decode('
one:
two:
') );
