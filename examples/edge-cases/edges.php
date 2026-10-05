<?php declare(strict_types=1);

/**
 * The corners of the format that a tool meets in real files, and what the library does with them.
 *
 * Usage: php examples/edge-cases/edges.php
 */

use Nette\Neon\Exception;
use Nette\Neon\Neon;

require __DIR__ . '/../bootstrap.php';

$show = function (string $label, string $text): void {
	try {
		printf("%-26s %s\n", $label, json_encode(Neon::decode($text)));
	} catch (Exception $e) {
		printf("%-26s %s\n", $label, $e->getMessage());
	}
};

$show('BOM and CRLF', "\u{FEFF}a: 1\r\nb: 2\r\n");
$show('dash subblock', "list:\n- a\n- b\nnext: 1");
$show('block on a dash, tab', "- a: 1\n\tb: 2");
$show('block on a dash, 2 spaces', "- a: 1\n  b: 2");
$show('block on a dash, 4 spaces', "- a: 1\n    b: 2");
$show('value on the next line', "a:\n\tx");
$show('multiline indentation', "a: '''\n\t\tdeeper\n\tshallower\n'''");
$show('words that are not bool', '[on, off, tRuE]');
$show('implicit keys', '{5: a, b}');
$show('invalid date', '2024-02-30');

// edits keep what is not edited, the missing final line ending included
$doc = Neon::parse("a: 1\r\nb: 2");
$doc->setValue(['c'], 3);
echo "\nadd to CRLF without EOL   ", json_encode($doc->print()), "\n";

// a value whose first line starts with spaces is written so that it reads back the same
$value = "  indented first line\nsecond";
echo 'encode leading spaces     ', json_encode(Neon::encode($value)), ' ', var_export(Neon::decode(Neon::encode($value)) === $value, true), "\n";
