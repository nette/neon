<?php declare(strict_types=1);

// Runs every example a chapter readme names under a heading and compares its output with the first plain
// fenced block under that heading; the PHP blocks of the main readme must at least compile.
// Usage: php examples/verify.php

$failures = $checked = 0;
$fail = function (string $message) use (&$failures): void {
	echo "FAIL $message\n";
	$failures++;
};

foreach (glob(__DIR__ . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
	$chapter = basename($dir);
	if (!is_file("$dir/readme.md")) {
		$fail("$chapter: no readme.md");
		continue;
	}

	$text = str_replace("\r\n", "\n", (string) file_get_contents("$dir/readme.md"));
	$quoted = [];
	preg_match_all('~^## [^\n]*\((\S+\.php)\)\n(.*?)(?=^## |\z)~ms', $text, $sections, PREG_SET_ORDER);
	foreach ($sections as [, $script, $body]) {
		if (preg_match('~^```\n(.*?)^```$~ms', $body, $block)) {
			$quoted[$script] = $block[1];
		} else {
			$fail("$chapter/$script: no output block under the heading");
		}
	}

	foreach (glob("$dir/*.php") ?: [] as $file) {
		$script = basename($file);
		if (!isset($quoted[$script])) {
			$fail("$chapter/$script: the readme quotes no output of it");
			continue;
		}
		$actual = str_replace("\r\n", "\n", (string) shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($file) . ' 2>&1'));
		$checked++;
		if ($actual !== $quoted[$script]) {
			$fail("$chapter/$script: the output differs from the readme\n--- expected\n{$quoted[$script]}--- actual\n$actual");
		}
		unset($quoted[$script]);
	}

	foreach (array_keys($quoted) as $script) {
		$fail("$chapter: the readme names $script, which does not exist");
	}
}

$readme = str_replace("\r\n", "\n", (string) file_get_contents(__DIR__ . '/../readme.md'));
preg_match_all('~^```php\n(.*?)^```$~ms', $readme, $blocks);
foreach ($blocks[1] as $code) {
	$file = tempnam(sys_get_temp_dir(), 'neon-readme');
	file_put_contents($file, "<?php\n" . $code);
	exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file) . ' 2>&1', $output, $status);
	unlink($file);
	$checked++;
	if ($status) {
		$fail('readme.md: ' . implode("\n", $output));
	}
	$output = [];
}

echo "$checked examples, $failures failures\n";
exit($failures ? 1 : 0);
