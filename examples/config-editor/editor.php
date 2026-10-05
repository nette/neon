<?php declare(strict_types=1);

/**
 * The core of a configuration editor: list the options of a file as the fields of a form, apply what the user
 * changed, and save a file whose diff is exactly those changes.
 *
 * Usage: php examples/config-editor/editor.php
 */

use Nette\Neon\Neon;
use Nette\Neon\Nodes\ArrayNode;
use Nette\Neon\Nodes\DocumentNode;

require __DIR__ . '/../bootstrap.php';

$file = sample('
	# Application settings
	# (edited by hand and by the admin panel)

	mail:
		host: smtp.example.com   # outgoing server
		port: 587
		secure: yes

	cache:
		enabled: true
		ttl: 3600   # seconds
		paths: [temp/cache]
');

$doc = Neon::parse($file);

// 1. the fields of the form: every scalar with its path, its type and its comment
function fields(DocumentNode $document): array
{
	$res = [];
	$walk = function (mixed $node, array $path) use (&$walk, &$res) {
		if (!$node instanceof ArrayNode) {
			return;
		}
		foreach ($node->items as $i => $item) {
			$itemPath = [...$path, $item->getKeyValue() ?? $i];
			if ($item->value instanceof ArrayNode) {
				$walk($item->value, $itemPath);
			} else {
				$comment = implode(' ', array_map(fn($c) => $c->getCommentText(), $item->getTrailingComments()));
				$res[] = ['path' => $itemPath, 'value' => $item->getValue(), 'comment' => $comment];
			}
		}
	};
	$walk($document->value, []);
	return $res;
}

foreach (fields($doc) as $field) {
	echo rtrim(sprintf('%-14s %-8s %-18s %s', implode('.', $field['path']), get_debug_type($field['value']), var_export($field['value'], true), $field['comment'])), "\n";
}

// 2. what the user submitted: all fields, most of them unchanged
$submitted = [
	'mail.host' => 'smtp.example.com',
	'mail.port' => 465,
	'mail.secure' => true,
	'cache.enabled' => false,
	'cache.ttl' => 3600,
	'cache.paths.0' => 'temp/cache',
];

foreach ($submitted as $key => $value) {
	$doc->setValue(explode('.', $key), $value);
}

// 3. save: only what changed is in the diff
echo "\n";
printDiff($file, $doc->print());
