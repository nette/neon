<?php declare(strict_types=1);

// The Nette Tester command-line runner can be
// invoked through the command: ../vendor/bin/tester .

if (@!include __DIR__ . '/../vendor/autoload.php') { // @ dependencies may not be installed
	echo 'Install Nette Tester using `composer install`';
	exit(1);
}


Tester\Environment::setup();
Tester\Environment::setupFunctions();
date_default_timezone_set('Europe/Prague');

// the corpus holds documents that raise the transitional notice for documents written for 3.x
$tester = set_error_handler(function (int $severity, string $message, string $file, int $line) use (&$tester): bool {
	return str_starts_with($message, 'Neon: the value on line ') || (bool) $tester($severity, $message, $file, $line);
});

require __DIR__ . '/helpers/corpus.php';
require __DIR__ . '/helpers/typed.php';
require __DIR__ . '/helpers/dumper.php';
