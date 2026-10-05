<?php declare(strict_types=1);

/**
 * Inline arrays: an item is added with the separator its neighbours use, on its own line in an array that spans
 * lines, and the trailing comma stays where it was.
 *
 * Usage: php examples/editing/inline.php
 */

use Nette\Neon\Neon;

require __DIR__ . '/../bootstrap.php';

$text = sample('
	roles: [guest, member]
	hosts: [
		alpha,
		beta,
	]
	limits: {cpu: 2, memory: 512}
	empty: []
');

$doc = Neon::parse($text);
$doc->addItem(['roles'], 'admin');
$doc->addItem(['roles'], 'root', position: 0);
$doc->addItem(['hosts'], 'gamma');
$doc->setValue(['limits', 'disk'], 100);
$doc->removeItem(['limits', 'cpu']);
$doc->addItem(['empty'], 'first');

printDiff($text, $doc->print());
