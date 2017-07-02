<?php

/**
 * Test: Nette\Neon\Neon::decode JSON compatibility.
 */

use Nette\Neon\Neon;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


Assert::same(['A' => 123], Neon::decode('{"A":123}'));


Assert::same([1, 2], Neon::decode("[1,2]"));
Assert::same([1, 2], Neon::decode("[1\n,2]"));
Assert::same([1, 2], Neon::decode("[1,\n2]"));
Assert::same([1, 2], Neon::decode("[1\n,\n2]"));


Assert::same(['A' => 'B'], Neon::decode("{\"A\":\"B\"}"));
Assert::same(['A' => 'B'], Neon::decode("{\"A\"\n:\"B\"}"));
Assert::same(['A' => 'B'], Neon::decode("{\"A\":\n\"B\"}"));
Assert::same(['A' => 'B'], Neon::decode("{\"A\"\n:\n\"B\"}"));
