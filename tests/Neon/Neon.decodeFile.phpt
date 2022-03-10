<?php

/**
 * Test: Nette\Neon\Neon::encode.
 */

declare(strict_types=1);

use Nette\Neon\Neon;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


Assert::exception(function () {
	Neon::decodeFile('unknown');
}, Nette\Neon\Exception::class, "Unable to read file 'unknown'. %a%");

Assert::same(
	['a', 'b'],
	Neon::decodeFile(__DIR__ . '/fixtures/file.neon'),
);
