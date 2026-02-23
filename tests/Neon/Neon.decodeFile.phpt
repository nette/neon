<?php declare(strict_types=1);

/**
 * Test: Nette\Neon\Neon::encode.
 */

use Nette\Neon\Neon;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


Assert::exception(
	fn() => Neon::decodeFile('unknown'),
	Nette\Neon\Exception::class,
	"Unable to read file 'unknown'. %a%",
);

Assert::same(
	['a', 'b'],
	Neon::decodeFile(__DIR__ . '/fixtures/file.neon'),
);
