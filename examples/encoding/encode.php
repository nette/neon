<?php declare(strict_types=1);

/**
 * Encoding PHP values as NEON, inline and as blocks, and which strings get quotes.
 *
 * Usage: php examples/encoding/encode.php
 */

use Nette\Neon\Entity;
use Nette\Neon\Neon;

require __DIR__ . '/../bootstrap.php';

$config = [
	'database' => ['dsn' => 'mysql:host=localhost', 'user' => 'root', 'options' => ['lazy' => true]],
	'services' => [new Entity('App\Mailer', ['@smtp', 'timeout' => 30])],
	'factory' => new Entity(Neon::Chain, [new Entity('@builder'), new Entity('::create', [1])]),
	'started' => new DateTimeImmutable('2016-06-03 19:00:00 +02:00'),
	'limit' => '12345678901234567890',
	'note' => "first line\nsecond line",
];

echo Neon::encode($config), "\n\n";
echo Neon::encode($config, blockMode: true), "\n";

// which arrays are blocks: only the first level, or all but lists
echo Neon::encode(['database' => $config['database'], 'hosts' => ['alpha', 'beta']], blockMode: fn($v, $path) => !$path), "\n";
echo Neon::encode(['database' => $config['database'], 'hosts' => ['alpha', 'beta']], blockMode: fn($v) => !array_is_list($v)), "\n";

// a string is written without quotes only if it reads back as the same string
foreach (['plain words', 'yes', 'On', '1.5', '-.5', '2016-06-03', 'a: b', 'a#b', 'a #b', '', ' padded', "tab\there", "bell\x07"] as $value) {
	printf("%-14s -> %s\n", json_encode($value), Neon::encode($value));
}
