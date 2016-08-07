<?php

/**
 * Test: Nette\Neon\Neon::decode JSON compatibility.
 */

use Nette\Neon\Neon;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


Assert::same(['A' => 123], Neon::decode('{"A":123}'));
