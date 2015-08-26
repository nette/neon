<?php

/**
 * Test: Nette\Neon\Neon::decode errors.
 */

declare(strict_types = 1);

use Nette\Neon\Neon;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


Assert::exception(function () {
	Neon::decode("Hello\nWorld");
}, Nette\Neon\Exception::class, "Unexpected 'World' on line 2, column 1.");


Assert::exception(function () {
	Neon::decode('"\uD801"');
}, Nette\Neon\Exception::class, 'Invalid UTF-8 (lone surrogate) \\uD801 on line 1, column 1.');


Assert::exception(function () {
	Neon::decode("- Dave,\n- Rimmer,\n- Kryten,\n");
}, Nette\Neon\Exception::class, "Unexpected ',' on line 1, column 7.");


Assert::exception(function () {
	Neon::decode('item [a, b]');
}, Nette\Neon\Exception::class, "Unexpected ',' on line 1, column 8.");


Assert::exception(function () {
	Neon::decode('{,}');
}, Nette\Neon\Exception::class, "Unexpected ',' on line 1, column 2.");


Assert::exception(function () {
	Neon::decode('{a, ,}');
}, Nette\Neon\Exception::class, "Unexpected ',' on line 1, column 5.");


Assert::exception(function () {
	Neon::decode('"');
}, Nette\Neon\Exception::class, "Unexpected '\"' on line 1, column 1.");


Assert::exception(function () {
	Neon::decode("\ta:\n b:");
}, Nette\Neon\Exception::class, 'Invalid combination of tabs and spaces on line 2, column 2.');


Assert::exception(function () {
	Neon::decode('
a:
  b:
 c: x
');
}, Nette\Neon\Exception::class, 'Bad indentation on line 4, column 2.');


Assert::exception(function () {
	Neon::decode('
a: 1
  b:
');
}, Nette\Neon\Exception::class, 'Bad indentation on line 3, column 3.');


Assert::exception(function () {
	Neon::decode('
- x:
 a: 10
');
}, Nette\Neon\Exception::class, 'Bad indentation on line 3, column 2.');


Assert::exception(function () {
	Neon::decode('
- x: 20
   a: 10
');
}, Nette\Neon\Exception::class, 'Bad indentation on line 3, column 4.');


Assert::exception(function () {
	Neon::decode('
- x: 20
 a: 10
');
}, Nette\Neon\Exception::class, 'Bad indentation on line 3, column 2.');


Assert::exception(function () {
	Neon::decode('- x: y:');
}, Nette\Neon\Exception::class, "Unexpected ':' on line 1, column 7.");


Assert::exception(function () {
	Neon::decode('
foo:
bar
');
}, Nette\Neon\Exception::class, "Unexpected '<new line>' on line 3, column 4.");
