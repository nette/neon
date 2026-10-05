<?php declare(strict_types=1);


/**
 * Decoding NEON into PHP values, and what an error says.
 *
 * Usage: php examples/parsing/decode.php
 */
use Nette\Neon\Exception;
use Nette\Neon\Neon;

require __DIR__ . '/../bootstrap.php';

$config = Neon::decode(sample('
	# a typical configuration
	application:
		debug: yes
		port: 0x1F90
		started: 2016-06-03 19:00:00 +02:00
		hosts: [alpha, beta]
	services:
		- Mailer(@smtp, timeout: 30)
	big: 12345678901234567890
'));

$app = $config['application'];
echo 'debug:   ', var_export($app['debug'], true), "\n";
echo 'port:    ', var_export($app['port'], true), "\n";
echo 'started: ', $app['started']::class, ' ', $app['started']->format('Y-m-d H:i:s P'), "\n";
echo 'hosts:   ', json_encode($app['hosts']), "\n";
$mailer = $config['services'][0];
echo 'service: ', $mailer::class, ' ', $mailer->value, ' ', json_encode($mailer->attributes), "\n";
echo 'big:     ', get_debug_type($config['big']), ' ', $config['big'], "\n";

try {
	Neon::decode("a: 1\n  b: 2");
} catch (Exception $e) {
	echo "\n", $e->getMessage(), "\n";
	echo $e->getDescription(), ' | line ', $e->position?->line, ', column ', $e->position?->column, "\n";
}
