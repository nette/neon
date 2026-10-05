<?php declare(strict_types=1);

/**
 * Removing items: an item goes with the comments that describe it, a section heading separated by a blank line
 * stays, and removing the last item leaves an empty collection, or nothing.
 *
 * Usage: php examples/editing/removing.php
 */

use Nette\Neon\CommentPolicy;
use Nette\Neon\Neon;

require __DIR__ . '/../bootstrap.php';

$text = sample('
	# --- Database ---

	database:
		host: localhost
		# legacy, will be removed
		driver: mysql   # see #123
		user: root

	# --- Mail ---
	mail:
		smtp: true
	queue:
		- send
');

// the comment above `driver` and the one after it describe the key, so they go with it
$doc = Neon::parse($text);
$doc->removeItem(['database', 'driver']);
printDiff($text, $doc->print());

// a heading right above the first key of a section belongs to that key ...
echo "\n";
$doc = Neon::parse($text);
$doc->removeItem(['mail']);
printDiff($text, $doc->print());

// ... unless you ask to keep it above the next item
echo "\n";
$doc = Neon::parse($text);
$doc->removeItem(['mail'], CommentPolicy::MoveToNextToken);
printDiff($text, $doc->print());

// the last item: the collection stays, empty; for an option where empty means nothing, mark it nullable
echo "\n";
$doc = Neon::parse($text);
$doc->removeItem(['queue', 0]);
$doc->findItem(['mail'])->nullable = true;
$doc->removeItem(['mail', 'smtp']);
printDiff($text, $doc->print());
