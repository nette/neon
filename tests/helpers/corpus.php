<?php declare(strict_types=1);

/**
 * The conformance corpus of the specification, loaded strictly: anything the loader does not know is a failure,
 * so that a broken corpus never looks green.
 */

namespace Tests;


final readonly class CorpusCase
{
	public function __construct(
		public string $name,
		public string $base,
		/** @var array<string, mixed> */
		public array $meta,
	) {
	}


	public function read(string $ext): ?string
	{
		return is_file($this->base . $ext) ? (string) file_get_contents($this->base . $ext) : null;
	}


	public function runsHere(): bool
	{
		$hosts = $this->meta['hosts'] ?? null;
		return !is_array($hosts) || in_array('php', $hosts, true);
	}
}


final class Corpus
{
	/** The version of the specification this implementation follows. */
	private const SpecVersion = '1.1.0';

	/** The files of a case in each category: the input, the required expectations, all allowed ones. */
	private const Categories = [
		'decode' => ['.neon', [['.json']], '~^\.json$~'],
		'errors' => ['.neon', [['.error']], '~^\.error$~'],
		'encode' => ['.json', [['.neon', '.error']], '~^\.(?:neon|block\.neon|error)$~'],
		'cst' => ['.neon', [['.dump']], '~^\.dump$~'],
		'roundtrip' => ['.neon', [['.dump']], '~^\.dump$~'],
		'mutations' => ['.neon', [['.ops.json'], ['.expected.neon']], '~^\.(?:ops\.json|expected(?:\.\d+)?\.(?:neon|json))$~'],
	];

	private const Hosts = ['js', 'php', 'py', 'go', 'rs'];


	public static function directory(): string
	{
		return getenv('NEON_CORPUS') ?: __DIR__ . '/../neon-spec/corpus';
	}


	/** @return list<CorpusCase> */
	public static function load(string $category, ?string $root = null): array
	{
		$root ??= self::directory();
		self::checkVersion($root);
		[$input, $required, $allowed] = self::Categories[$category] ?? throw new \LogicException("Unknown category $category");
		$dir = "$root/$category";
		if (!is_dir($dir)) {
			throw new \LogicException("Missing category $dir; run composer spec");
		}

		$files = array_values(array_diff(scandir($dir) ?: [], ['.', '..']));
		$names = [];
		foreach ($files as $file) {
			$name = strstr($file, '.', true) ?: $file;
			if ($name . $input === $file) {
				$names[] = $name;
			}
		}
		if (!$names) {
			throw new \LogicException("Empty category $dir");
		}
		sort($names);
		foreach ($files as $file) {
			$name = strstr($file, '.', true) ?: $file;
			$ext = substr($file, strlen($name));
			if ($ext !== $input && $ext !== '.case.json' && !preg_match($allowed, $ext)) {
				throw new \LogicException("Unknown file $dir/$file");
			} elseif (!in_array($name, $names, true)) {
				throw new \LogicException("An expectation without its input: $dir/$file");
			}
		}

		$cases = [];
		foreach ($names as $name) {
			$base = "$dir/$name";
			foreach ($required as $group) {
				if (!array_filter($group, fn($ext) => is_file($base . $ext))) {
					throw new \LogicException('Missing ' . implode(' or ', $group) . " of $base");
				}
			}
			$cases[] = new CorpusCase($name, $base, self::readMeta("$base.case.json", $category));
		}
		return $cases;
	}


	private static function checkVersion(string $root): void
	{
		$file = "$root/../spec/VERSION";
		if (!is_file($file)) {
			throw new \LogicException("Missing $file; the corpus belongs to a checkout of the specification.");
		} elseif (($version = trim((string) file_get_contents($file))) !== self::SpecVersion) {
			throw new \LogicException('The corpus is of specification ' . $version . ', this implementation follows ' . self::SpecVersion . '.');
		}
	}


	/** @return array<string, mixed> */
	private static function readMeta(string $file, string $category): array
	{
		if (!is_file($file)) {
			return ['schema' => 1];
		}
		$meta = json_decode((string) file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);
		$fail = fn(string $message) => throw new \LogicException("$message in $file");
		if (!is_array($meta)) {
			$fail('Not an object');
		}
		foreach (array_keys($meta) as $key) {
			if (!in_array($key, ['schema', 'hosts', 'php3', 'reason', 'indentation', 'blockMode'], true)) {
				$fail("Unknown field '$key'");
			}
		}
		$hosts = $meta['hosts'] ?? null;
		$reason = $meta['reason'] ?? null;
		$blockMode = $meta['blockMode'] ?? null;
		match (true) {
			($meta['schema'] ?? null) !== 1 => $fail('Unknown schema'),
			$hosts !== null && (!is_array($hosts) || !$hosts || array_diff($hosts, self::Hosts)) => $fail('Invalid hosts'),
			($meta['php3'] ?? null) === 'open' => $fail('An open deviation from nette/neon 3.x'),
			isset($meta['php3']) && !in_array($meta['php3'], ['fixed', 'host'], true) => $fail('Invalid php3'),
			($hosts !== null || isset($meta['php3'])) && (!is_string($reason) || trim($reason) === '') => $fail('A sidecar without reason'),
			isset($meta['indentation'])
				&& (!is_string($meta['indentation'])
					|| !preg_match('~^[\t ]+$~D', $meta['indentation'])
					|| $category !== 'encode') => $fail('Invalid indentation'),
			$blockMode !== null
				&& ((!is_bool($blockMode) && !(is_int($blockMode) && $blockMode >= 0))
					|| $category !== 'encode') => $fail('Invalid blockMode'),
			default => null,
		};
		return $meta;
	}
}
