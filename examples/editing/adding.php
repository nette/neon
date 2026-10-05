<?php declare(strict_types=1);

/**
 * Adding items: a new key gets the indentation of its siblings, a list item its dash, a missing section is
 * created under the key that exists, and a typo in a path fails instead of adding a key.
 *
 * Usage: php examples/editing/adding.php
 */

use Nette\Neon\Exception;
use Nette\Neon\Neon;

require __DIR__ . '/../bootstrap.php';

$text = sample('
	parameters:
	    mail: info@example.com

	services:
		- App\Model\UserFacade

	extensions:
');

$doc = Neon::parse($text);
$doc->setValue(['parameters', 'admin'], 'admin@example.com');
$doc->addItem(['services'], 'App\Model\OrderFacade');
$doc->setValue(['extensions', 'console'], 'Contributte\Console\DI\ConsoleExtension');

printDiff($text, $doc->print());

try {
	$doc->setValue(['parametres', 'admin'], 'x');
} catch (Exception $e) {
	echo "\n", $e->getMessage(), "\n";
}
try {
	$doc->setValue(['parameters', 'amdin'], 'x', create: false);
} catch (Exception $e) {
	echo $e->getMessage(), "\n";
}
