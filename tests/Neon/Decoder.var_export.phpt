<?php

/**
 * Test: Nette\Neon\Neon::decode var_export() support.
 */

declare(strict_types=1);

use Nette\Neon\Neon;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

$parsed = Neon::decode('
person:
	id:   	100
	data:   Andares(gender=male, married=yes)
');

$serialized = var_export($parsed, true);
$unserialized = eval("return $serialized;");

Assert::equal($parsed, $unserialized);
