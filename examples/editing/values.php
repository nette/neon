<?php declare(strict_types=1);

/**
 * Changing values: the comment after a value stays, a number keeps its base, a boolean its word, a string its
 * quotes, and writing a value that is already there changes nothing.
 *
 * Usage: php examples/editing/values.php
 */

use Nette\Neon\Neon;

require __DIR__ . '/../bootstrap.php';

$text = sample('
	# database connection
	database:
		host: localhost   # the server
		port: 0x0CEA
		debug: yes
		user: \'root\'
		password: "s3cret"

	timeout: 30
');

$doc = Neon::parse($text);
$doc->setValue(['database', 'host'], 'db.example.com');
$doc->setValue(['database', 'port'], 3307);
$doc->setValue(['database', 'debug'], false);
$doc->setValue(['database', 'user'], 'admin');
$doc->setValue(['database', 'password'], 'it\'s "new"');
$doc->setValue(['timeout'], 30); // the same value: no change at all

printDiff($text, $doc->print());
