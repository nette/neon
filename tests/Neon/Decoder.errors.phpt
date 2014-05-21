<?php

/**
 * Test: Nette\Neon\Neon::decode errors.
 *
 * @author     David Grudl
 */

use Nette\Neon\Neon,
	Tester\Assert;


require __DIR__ . '/../bootstrap.php';


Assert::exception(function() {
	Neon::decode("Hello\nWorld");
}, 'Nette\Neon\Exception', "Unexpected 'World' on line 2, column 1." );


Assert::exception(function() {
	Neon::decode("- Dave,\n- Rimmer,\n- Kryten,\n");
}, 'Nette\Neon\Exception', "Unexpected ',' on line 1, column 7." );


Assert::exception(function() {
	Neon::decode("- first: Dave\n last: Lister\n gender: male\n");
}, 'Nette\Neon\Exception', "Unexpected ':' on line 1, column 8." );


Assert::exception(function() {
	Neon::decode('item [a, b]');
}, 'Nette\Neon\Exception', "Unexpected ',' on line 1, column 8." );


Assert::exception(function() {
	Neon::decode('{,}');
}, 'Nette\Neon\Exception', "Unexpected ',' on line 1, column 2." );


Assert::exception(function() {
	Neon::decode('{a, ,}');
}, 'Nette\Neon\Exception', "Unexpected ',' on line 1, column 5." );


Assert::exception(function() {
	Neon::decode('"');
}, 'Nette\Neon\Exception', "Unexpected '\"' on line 1, column 1." );
