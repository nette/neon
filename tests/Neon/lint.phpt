<?php declare(strict_types=1);

/**
 * Test: neon-lint.
 */

use Nette\Neon\Linter;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


$dir = sys_get_temp_dir() . '/neon-lint-' . getmypid();
@mkdir("$dir/sub", 0o777, true); // @ the directory may exist
@mkdir("$dir/vendor"); // @ the directory may exist
@mkdir("$dir/.hidden"); // @ the directory may exist
file_put_contents("$dir/valid.neon", "a: 1\n");
file_put_contents("$dir/invalid.neon", "a: 1\n  b: 2\n");
file_put_contents("$dir/sub/utf8.neon", "a: \xC3\x28");
file_put_contents("$dir/vendor/skipped.neon", '{');
file_put_contents("$dir/.hidden/skipped.neon", '{');
file_put_contents("$dir/other.txt", '{');
register_shutdown_function(function () use ($dir) {
	Tester\Helpers::purge($dir);
	rmdir($dir);
});


/** Runs the linter: the exit code, the output with the directory written <dir>, the error output. */
$run = function (array $args, string $stdin = '', bool $raw = false) use ($dir): array {
	$out = $err = '';
	$code = new Linter(
		function (string $text) use (&$out) { $out .= $text; },
		function (string $text) use (&$err) { $err .= $text; },
		fn() => $stdin,
	)->run($args);
	return [$code, $raw ? $out : str_replace([$dir, '\\'], ['<dir>', '/'], $out), $err];
};


test('valid files', function () use ($dir, $run) {
	Assert::same([0, '', ''], $run(["$dir/valid.neon"]));
	Assert::same([0, "OK 1 files\n", ''], $run(['--verbose', "$dir/valid.neon"]));
	Assert::same(0, $run(['--check', "$dir/valid.neon", "$dir/valid.neon"])[0]);
});


test('findings in a directory, sorted, skipping vendor and hidden directories', function () use ($dir, $run) {
	Assert::same([1, "<dir>/invalid.neon:2:3: Bad indentation\n<dir>/sub/utf8.neon:1:4: Invalid UTF-8 sequence.\n", ''], $run([$dir]));
	Assert::same([1, '', ''], $run(['--quiet', $dir]));
	Assert::same(
		[['path' => "$dir/invalid.neon", 'line' => 2, 'column' => 3, 'message' => 'Bad indentation']],
		json_decode($run(['--json', "$dir/invalid.neon"], raw: true)[1], true),
	);
});


test('standard input', function () use ($run) {
	Assert::same([1, "-:1:5: Unexpected end\n", ''], $run(['-'], 'a: ['));
	Assert::same(0, $run(['-'], 'a: 1')[0]);
	Assert::noError(fn() => Assert::same([0, '', ''], $run(['-'], "{a:\n\tb}")));
});


test('wrong invocations', function () use ($dir, $run) {
	Assert::same(2, $run([])[0]);
	Assert::same(2, $run(['--bogus', $dir])[0]);
	[$code, $out, $err] = $run(['--dump', '-'], 'a');
	Assert::same([2, ''], [$code, $out]);
	Assert::contains('Unknown option --dump', $err);
	Assert::same([1, "<dir>/missing: No file found\n", ''], $run(["$dir/missing"]));
	Assert::same(0, $run(['--help'])[0]);
});


test('a path given that does not exist is a finding, whatever else is valid', function () use ($dir, $run) {
	Assert::same([1, "<dir>/missing.neon: No file found\n", ''], $run(["$dir/missing.neon", '-'], 'a: 1'));
	Assert::same(1, $run(["$dir/valid.neon", "$dir/missing.neon"])[0]);
});


test('one file under two paths is linted once, the files in the order of their paths', function () use ($dir, $run) {
	Assert::same([0, "OK 1 files\n", ''], $run(['--verbose', "$dir/valid.neon", "$dir/sub/../valid.neon"]));
	Assert::same(
		"<dir>/invalid.neon:2:3: Bad indentation\n<dir>/sub/utf8.neon:1:4: Invalid UTF-8 sequence.\n",
		$run(["$dir/sub", "$dir/invalid.neon"])[1],
	);
});


test('the round trip is checked', function () use ($run) {
	Assert::same([0, '', ''], $run(['--check', '-'], "a: 1 # c\r\nb:\r\n\t- x\n"));
});


test('the binary', function () use ($dir) {
	exec(PHP_BINARY . ' ' . escapeshellarg(__DIR__ . '/../../bin/neon-lint') . ' ' . escapeshellarg("$dir/invalid.neon"), $output, $code);
	Assert::same(1, $code);
	Assert::same(
		[str_replace('\\', '/', "$dir/invalid.neon") . ':2:3: Bad indentation'],
		array_map(fn($line) => str_replace('\\', '/', $line), $output),
	);
});
