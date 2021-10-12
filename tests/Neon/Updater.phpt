<?php

/** @phpVersion 7.2 */

declare(strict_types=1);

use Nette\Neon;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


function testUpdate(string $old, string $new, string $expected): void
{
	$updater = new Neon\Updater($old);
	$newValue = Neon\Neon::decode($new);
	$res = $updater->updateValue($newValue);
	Assert::match($expected, $res);
}


$old = <<<'XX'
# begin on document
first: # first comment
	# another comment
	- a  # a comment
	- b
	- c
# inner-comment 1
second:
	sub:
		a: 1
		b: 2
# inner-comment 2
third:
	x: y
# end on document
XX;

$new = <<<'XX'
first:
	- x
	- b
second:
	sub:
		a: 5
		b: 2
	new: 3
fourth:
XX;

$res = <<<'XX'
# begin on document
first: # first comment
	# another comment
	- x
	- b
# inner-comment 1
second:
	sub:
		a: 5
		b: 2
	new: 3
fourth: null
# inner-comment 2
# end on document
XX;
testUpdate($old, $new, $res);



$old = <<<'XX'
# entity test
	entity: foo(  #xxx
		10,
		20,
	)
	chain: aa(10,20)bb(x)cc
# end of document
XX;

$new = <<<'XX'
entity: bar(
		10,
		30,
	)
chain: ax(10)bx(x,y)cc
XX;

$res = <<<'XX'
# entity test
	entity: bar(10, 30)
	chain: ax(10)bx(x, y)cc()
# end of document
XX;
testUpdate($old, $new, $res);



$old = <<<'XX'
# inline arrays
foo:
	- [k,
		l, m, n]
XX;

$new = <<<'XX'
foo:
	- [k, l, ]
XX;

$res = <<<'XX'
# inline arrays
foo:
	- [k, l]
XX;
testUpdate($old, $new, $res);
