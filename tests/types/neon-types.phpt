<?php declare(strict_types=1);

use Nette\PHPStan\Tester\TypeAssert;

require __DIR__ . '/../bootstrap.php';

TypeAssert::assertTypes(__DIR__ . '/neon-types.php');
