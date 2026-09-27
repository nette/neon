<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Neon;

use function count, is_array;
use const JSON_INVALID_UTF8_SUBSTITUTE, JSON_THROW_ON_ERROR, JSON_UNESCAPED_SLASHES, JSON_UNESCAPED_UNICODE;


/**
 * The command line linter, neon-lint.
 * @internal
 * @phpstan-type Finding  array{path: string, line: ?int, column: ?int, message: string}
 */
final readonly class Linter
{
	private const Usage = <<<'XX'
		Usage: neon-lint [options] <path>...

		Checks NEON files; a path is a file, a directory (searched for *.neon) or - for stdin.

		Options:
		  --check     also verify that printing the parsed tree gives back the file
		  --json      print the findings as JSON
		  --quiet     print nothing, only set the exit code
		  --verbose   print the number of files when all are valid

		XX;

	private const Options = ['--check', '--json', '--quiet', '--verbose', '--help'];
	private const SkippedDirectories = ['vendor', 'node_modules'];


	public function __construct(
		/** @var \Closure(string): void */
		private \Closure $out,
		/** @var \Closure(string): void */
		private \Closure $err,
		/** @var \Closure(): string */
		private \Closure $stdin,
	) {
	}


	/** @param  list<string>  $args */
	public static function main(array $args): int
	{
		$linter = new self(
			function (string $text): void { fwrite(STDOUT, $text); },
			function (string $text): void { fwrite(STDERR, $text); },
			fn() => (string) stream_get_contents(STDIN),
		);
		return $linter->run($args);
	}


	/**
	 * Runs the linter and returns the exit code.
	 * @param  list<string>  $args
	 */
	public function run(array $args): int
	{
		$options = array_values(array_filter($args, fn($arg) => str_starts_with($arg, '--')));
		$paths = array_values(array_filter($args, fn($arg) => !str_starts_with($arg, '--')));
		$unknown = array_values(array_diff($options, self::Options))[0] ?? null;
		if (in_array('--help', $options, true)) {
			($this->out)(self::Usage);
			return 0;
		} elseif ($unknown !== null || !$paths) {
			($this->err)(($unknown !== null ? "Unknown option $unknown\n\n" : '') . self::Usage);
			return 2;
		}

		[$files, $findings] = $this->collect($paths);
		if (!$files && !$findings) {
			$findings[] = ['path' => implode(' ', $paths), 'line' => null, 'column' => null, 'message' => 'No file found'];
		}
		foreach ($files as $file) {
			try {
				if ($finding = $this->lintFile($file, in_array('--check', $options, true))) {
					$findings[] = $finding;
				}
			} catch (\LogicException|\RuntimeException $e) {
				return $this->reportInternalError($file, $e);
			}
		}

		usort($findings, fn($a, $b) => strcmp($a['path'], $b['path']));
		if (in_array('--quiet', $options, true)) {
			// nothing
		} elseif (in_array('--json', $options, true)) {
			$flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR;
			($this->out)(json_encode($findings, $flags) . "\n");
		} elseif ($findings) {
			($this->out)(implode("\n", array_map(self::formatFinding(...), $findings)) . "\n");
		} elseif (in_array('--verbose', $options, true)) {
			($this->out)('OK ' . count($files) . " files\n");
		}
		return $findings ? 1 : 0;
	}


	/** @param  Finding  $finding */
	private static function formatFinding(array $finding): string
	{
		return $finding['line'] === null
			? "$finding[path]: $finding[message]"
			: "$finding[path]:$finding[line]:$finding[column]: $finding[message]";
	}


	private function reportInternalError(string $path, \Throwable $e): int
	{
		($this->err)("Internal error in $path: {$e->getMessage()}\nPlease report it at https://github.com/nette/neon/issues\n");
		return 2;
	}


	/**
	 * The text of a file, or the finding why it cannot be read.
	 * @return string|Finding
	 */
	private function read(string $path): string|array
	{
		$input = $path === '-' ? ($this->stdin)() : @file_get_contents($path); // @ is turned into a finding
		return $input === false
			? [
				'path' => $path,
				'line' => null,
				'column' => null,
				'message' => preg_replace('#^\w+\(.*?\): #', '', error_get_last()['message'] ?? 'Cannot read the file'),
			]
			: $input;
	}


	/** @return ?Finding */
	private function lintFile(string $path, bool $check): ?array
	{
		$input = $this->read($path);
		if (is_array($input)) {
			return $input;
		}

		try {
			$document = Neon::parse($input);
			if ($check && ($printed = $document->print()) !== $input) {
				$i = strspn($printed ^ $input, "\0");
				$position = new Position()->advance(mb_strcut($input, 0, $i, 'UTF-8'));
				return [
					'path' => $path,
					'line' => $position->line,
					'column' => $position->column,
					'message' => 'Printing the tree differs from the file',
				];
			}
		} catch (Exception $e) {
			return ['path' => $path, 'line' => $e->position?->line, 'column' => $e->position?->column, 'message' => $e->getDescription()];
		}
		return null;
	}


	/**
	 * The files to lint, sorted by path, each once even when two paths name it; a path given that does not exist,
	 * or a directory that cannot be read, is a finding. The recursion does not follow links, a path given is followed.
	 * @param  list<string>  $paths
	 * @return array{list<string>, list<Finding>}
	 */
	private function collect(array $paths): array
	{
		$files = $findings = []; // identity of the file => the path shown
		$add = function (string $path) use (&$files): void {
			$identity = $path === '-' ? $path : (realpath($path) ?: $path);
			$files[$identity] ??= $path;
		};
		$walk = function (string $dir) use (&$walk, &$findings, $add): void {
			$entries = @scandir($dir); // @ is turned into a finding
			if ($entries === false) {
				$findings[] = [
					'path' => $dir,
					'line' => null,
					'column' => null,
					'message' => preg_replace('#^\w+\(.*?\): #', '', error_get_last()['message'] ?? 'Cannot read the directory'),
				];
				return;
			}
			foreach ($entries as $entry) {
				$full = rtrim($dir, '/\\') . '/' . $entry; // the same output on every system
				if ($entry[0] === '.' || in_array($entry, self::SkippedDirectories, true) || is_link($full)) {
					continue;
				} elseif (is_dir($full)) {
					$walk($full);
				} elseif (str_ends_with($entry, '.neon') && is_file($full)) {
					$add($full);
				}
			}
		};

		foreach ($paths as $path) {
			if ($path === '-' || is_file($path)) {
				$add($path);
			} elseif (is_dir($path)) {
				$walk($path);
			} else {
				$findings[] = [
					'path' => $path,
					'line' => null,
					'column' => null,
					'message' => file_exists($path) ? 'Not a file or a directory' : 'No file found',
				];
			}
		}
		$files = array_values($files);
		sort($files, SORT_STRING);
		return [$files, $findings];
	}
}
