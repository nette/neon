<?php

declare(strict_types=1);

use Nette\Neon\Neon;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


Assert::equal((object) [
	'a' => [1, 2, 3],
	'b' => [],
	'c' => (object) [
		'a' => 1,
		0 => 2,
		1 => 3,
	],
], Neon::decode('
a: {1, 2, 3}
b: []
c: [a: 1, 2, 3]', true));
