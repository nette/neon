<?php

/**
 * Test: Nette\Neon\Neon::decode var_export() support.
 */

use Nette\Neon\{Neon, Entity};
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

$parsed = Neon::decode('
person:
    id:   	100
    data:   Andares(gender=male, married=yes)');

$cache 	= var_export($parsed, true);
$loaded	= eval("return $cache;");

Assert::same($parsed['person']['data']->attributes['gender'],
	$loaded['person']['data']->attributes['gender']);
