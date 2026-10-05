<?php declare(strict_types=1);

/**
 * Parsing into the syntax tree and printing it back: byte for byte, whatever the file looks like.
 *
 * Usage: php examples/parsing/roundtrip.php
 */

use Nette\Neon\Neon;

require __DIR__ . '/../bootstrap.php';

$files = [
	"\u{FEFF}# BOM, CRLF, a comment at the end\r\nkey: value # here\r\n",
	"tabs:\n\t- a\nspaces:\n    - b: 1\n      # a comment where you would not expect one\n      c: 2\n",
	'no final line ending:   [a ,b,c ,]',
	"multiline: '''\n\t  keeps\n\tits indentation\n'''\n\n\n",
];

foreach ($files as $text) {
	$document = Neon::parse($text);
	echo var_export($document->print() === $text, true), ' ', json_encode($document->toValue()), "\n";
}
