<?php declare(strict_types=1);

/**
 * neon-lint: findings with positions, a verdict in the exit code, JSON for tools. Here it runs from code over
 * files of a temporary directory; on the command line it is `vendor/bin/neon-lint config/`.
 *
 * Usage: php examples/linting/lint.php
 */

use Nette\Neon\Linter;

require __DIR__ . '/../bootstrap.php';

$dir = sys_get_temp_dir() . '/neon-lint-example-' . getmypid();
mkdir("$dir/config", 0o777, true);
file_put_contents("$dir/config/common.neon", "database:\n\tdsn: sqlite:db.sqlite\n");
file_put_contents("$dir/config/local.neon", "database:\n\tuser: root\n\tuser: admin\n");
file_put_contents("$dir/config/services.neon", "services:\n\t- App\\Mailer(\n");
chdir($dir);

$print = function (string $text): void { echo $text; };
$linter = new Linter($print, $print, fn() => '');
$run = function (array $args) use ($linter): void {
	echo '$ neon-lint ', implode(' ', $args), "\n";
	$code = $linter->run($args);
	echo "exit code $code\n\n";
};

$run(['config']);
$run(['--json', 'config/local.neon']);
$run(['--verbose', 'config/common.neon']);

chdir(sys_get_temp_dir());
array_map(unlink(...), glob("$dir/config/*"));
rmdir("$dir/config");
rmdir($dir);
