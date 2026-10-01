<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Neon;

use function count, is_array, is_bool, is_float, is_int, is_object, is_string, strlen;
use const JSON_THROW_ON_ERROR, JSON_UNESCAPED_SLASHES, JSON_UNESCAPED_UNICODE;


/**
 * Writes a PHP value as NEON. Every array, object and iterator is read once into a list of entries,
 * and the text is written from it.
 * @internal
 */
final class Encoder
{
	#[\Deprecated]
	public const BLOCK = true;

	/** @var list<array{?int, bool}> the containers on the way to the value: the id of an object, whether it is an array */
	private array $stack = [];

	/** @var array<int, true> the ids of the objects on the way */
	private array $objects = [];
	private int $arrays = 0;

	/** @var list<string> */
	private array $path = [];


	public function __construct(
		/** @var bool|\Closure(mixed, list<string>): bool */
		private readonly bool|\Closure $blockMode = false,
		private readonly string $indentation = "\t",
		/** Writes multiline strings in """, for a document where ''' would close an earlier string. */
		private readonly bool $doubleQuotedMultiline = false,
	) {
		if (!preg_match('~^[\t ]+$~D', $indentation)) {
			throw new \InvalidArgumentException('The indentation is a non-empty run of spaces and tabs.');
		}
	}


	/**
	 * @throws Exception  for a value that cannot be written as NEON
	 */
	public function encode(mixed $value): string
	{
		$array = $this->materialize($value);
		if ($array === null || !$this->isBlock($value)) {
			return $this->inline($value, $array);
		} elseif (!$array[0]) {
			return $array[1] ? '{}' : '[]';
		}
		return $this->block($value, $array[0]);
	}


	/** @param  list<array{string, mixed}>  $entries */
	private function block(mixed $container, array $entries): string
	{
		$res = '';
		$this->enter($container, array: true);
		foreach (self::hideKeys($entries) as [$key, $value, $hidden]) {
			$prefix = $hidden ? '-' : $this->formatKey($key) . ':';
			$this->path[] = $key;
			$nested = $this->materialize($value);
			if ($nested && $nested[0] && $this->isBlock($value)) {
				$text = self::indent($this->block($value, $nested[0]), $this->indentation);
				$res .= $prefix . "\n" . $text . (str_ends_with($text, "\n\n") ? '' : "\n");
			} else {
				$res .= $prefix . ' ' . $this->inline($value, $nested) . "\n";
			}
			array_pop($this->path);
		}
		$this->leave();
		return $res;
	}


	/** @param  ?array{list<array{string, mixed}>, bool}  $array  the entries of the value, whether it is a map */
	private function inline(mixed $value, ?array $array): string
	{
		if ($value instanceof Entity) {
			$this->enter($value);
			$res = $value->value === Neon::Chain ? $this->formatChain($value) : $this->formatEntity($value);
			$this->leave();
			return $res;
		} elseif ($array) {
			return ($array[1] ? '{' : '[') . $this->formatItems($value, $array[0]) . ($array[1] ? '}' : ']');
		}
		return $this->formatScalar($value);
	}


	private function formatEntity(Entity $entity, bool $link = false): string
	{
		if ($entity->value instanceof Entity) {
			$this->fail('the value of an entity cannot be an entity or a chain');
		}
		$head = $this->inline($entity->value, $this->materialize($entity->value));
		if ($link && preg_match('~^[\'"[{]~', $head)) {
			$this->fail('a link of a chain after the first needs a head written as a literal');
		}
		return $head . '(' . $this->formatItems($entity->attributes, $this->materialize($entity->attributes)[0] ?? []) . ')';
	}


	private function formatChain(Entity $chain): string
	{
		$links = array_filter($chain->attributes, fn($link) => $link instanceof Entity && $link->value !== Neon::Chain);
		if (!array_is_list($chain->attributes) || count($links) < 2 || count($links) !== count($chain->attributes)) {
			$this->fail('a chain must hold a list of two or more entities');
		}
		$res = '';
		foreach ($links as $i => $link) {
			$this->enter($link);
			$res .= $this->formatEntity($link, link: $i > 0);
			$this->leave();
		}
		return $res;
	}


	/** @param  list<array{string, mixed}>  $entries */
	private function formatItems(mixed $container, array $entries): string
	{
		$res = [];
		$this->enter($container, array: true);
		foreach (self::hideKeys($entries) as [$key, $value, $hidden]) {
			$prefix = $hidden ? '' : $this->formatKey($key) . ': ';
			$this->path[] = $key;
			$res[] = $prefix . $this->inline($value, $this->materialize($value));
			array_pop($this->path);
		}
		$this->leave();
		return implode(', ', $res);
	}


	private function formatKey(string $key): string
	{
		return Scalars::isIntegerKey($key) ? $key : $this->formatString($key);
	}


	private function formatScalar(mixed $value): string
	{
		return match (true) {
			$value === null => 'null',
			is_bool($value) => $value ? 'true' : 'false',
			is_int($value) => (string) $value,
			is_float($value) => self::formatFloat($value) ?? throw new Exception('INF and NAN cannot be encoded to NEON'),
			is_string($value) => $this->formatString($value),
			$value instanceof \DateTimeInterface => $this->formatDate($value),
			default => $this->fail(get_debug_type($value) . ' cannot be encoded'),
		};
	}


	private function formatString(string $s): string
	{
		if (!preg_match('//u', $s)) {
			$this->fail('a string is not valid UTF-8');
		} elseif (str_contains($s, "\n")) {
			return $this->formatMultiline($s);
		} elseif (!self::needsQuotes($s)) {
			return $s;
		} elseif (preg_match('~[\x00-\x08\x0B-\x1F]~', $s)) {
			return self::formatJson($s);
		}
		return "'" . str_replace("'", "''", $s) . "'";
	}


	private function formatMultiline(string $s): string
	{
		$delimiter = "'''";
		$content = $s;
		$firstLine = preg_match('~^\n*([^\n]*)~', $s, $m) ? $m[1] : '';
		if (
			$this->doubleQuotedMultiline
			|| preg_match('~[\x00-\x08\x0B-\x1F]~', $s)
			|| preg_match("~(?:^|\n)[\t ]*'''~", $s)
			|| preg_match('~^[\t ]~', $firstLine)
		) {
			$delimiter = '"""';
			$content = preg_replace_callback(
				'~\\\(.)~',
				fn(array $m) => ['n' => "\n", 't' => "\t", '"' => '"'][$m[1]] ?? $m[0],
				substr(self::formatJson($s), 1, -1),
			);
			$content = str_replace('"""', '""\"', $content);
			$content = preg_replace_callback(
				'~^(\n*)([\t ]+)~',
				fn(array $m) => $m[1] . str_replace([' ', "\t"], ['\\u0020', '\t'], $m[2]),
				$content,
			);
		}
		return $delimiter . "\n" . self::indent($content, $this->indentation) . "\n" . $delimiter;
	}


	private function formatDate(\DateTimeInterface $date): string
	{
		$year = (int) $date->format('Y');
		if ($year < 0 || $year > 9999) {
			$this->fail('a date out of the years 0 to 9999');
		}
		return $date->format('Y-m-d H:i:s O');
	}


	/**
	 * The entries of an array, an iterator or an object, and whether it is a map; null for any other value.
	 * @return ?array{list<array{string, mixed}>, bool}
	 */
	private function materialize(mixed $value): ?array
	{
		if (is_array($value)) {
			$res = [];
			foreach ($value as $k => $v) {
				$res[] = [(string) $k, $v];
			}
			return [$res, !array_is_list($value)];

		} elseif ($value instanceof \Traversable) {
			$res = $seen = [];
			foreach ($value as $k => $v) {
				if (!is_int($k) && !is_string($k)) {
					$this->fail('a key of an iterator must be int or string, ' . get_debug_type($k) . ' given');
				} elseif (isset($seen[$k])) {
					$this->fail("the iterator gives the key '$k' twice");
				}
				$seen[$k] = true;
				$res[] = [(string) $k, $v];
			}
			return [$res, array_keys($seen) !== array_keys($res)];

		} elseif (
			is_object($value)
			&& !$value instanceof Entity
			&& !$value instanceof \DateTimeInterface
			&& !$value instanceof \Closure
			&& !$value instanceof Node
			&& !$value instanceof Token
		) {
			$res = [];
			foreach (get_object_vars($value) as $k => $v) {
				$res[] = [(string) $k, $v];
			}
			return [$res, true];
		}
		return null;
	}


	/** Whether the array is written as a block. */
	private function isBlock(mixed $value): bool
	{
		return $this->blockMode instanceof \Closure ? ($this->blockMode)($value, $this->path) : $this->blockMode;
	}


	/** Goes into a container, an array being one level of nesting. */
	private function enter(mixed $container, bool $array = false): void
	{
		$id = is_object($container) ? spl_object_id($container) : null;
		if ($id !== null && isset($this->objects[$id])) {
			$this->fail('a cycle cannot be encoded');
		} elseif ($array && $this->arrays >= Parser::MaxDepth) {
			$this->fail('arrays nested deeper than ' . Parser::MaxDepth . ' levels');
		} elseif ($id !== null) {
			$this->objects[$id] = true;
		}
		$this->stack[] = [$id, $array];
		$this->arrays += (int) $array;
	}


	private function leave(): void
	{
		[$id, $array] = array_pop($this->stack) ?? throw new \LogicException;
		unset($this->objects[$id ?? -1]);
		$this->arrays -= (int) $array;
	}


	private function fail(string $reason): never
	{
		throw new Exception('Cannot encode: ' . $reason . ($this->path ? ' at ' . implode('.', $this->path) : ''));
	}


	/**
	 * Marks the keys that decoding would assign anyway.
	 * @param  list<array{string, mixed}>  $entries
	 * @return list<array{string, mixed, bool}>
	 */
	private static function hideKeys(array $entries): array
	{
		$res = [];
		$counter = 0;
		$hiding = true;
		foreach ($entries as [$key, $value]) {
			$integer = Scalars::isIntegerKey($key);
			$res[] = [$key, $value, $hiding && $integer && (int) $key === $counter];
			if ($integer && $hiding) {
				$k = (int) $key;
				$hiding = $k === $counter;
				$counter = max($k < PHP_INT_MAX ? $k + 1 : $k, $counter);
			}
		}
		return $res;
	}


	/** Whether the string has to be quoted. */
	private static function needsQuotes(string $s): bool
	{
		return !Lexer::isLiteral($s)
			|| preg_match('~[\x00-\x1F]~', $s)
			|| preg_match('~^[+\-.]?\d~', $s)
			|| preg_match('~^(?:true|false|yes|no|on|off|null)$~iD', $s)
			|| Scalars::evaluateLiteral($s) !== $s;
	}


	private static function formatJson(string $s): string
	{
		return json_encode($s, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
	}


	/** Shortest digits that read back as the same double, whatever the ini settings and the locale; null for INF and NAN. */
	private static function formatFloat(float $f): ?string
	{
		if (!is_finite($f)) {
			return null;
		} elseif ($f === 0.0) {
			return fdiv(1, $f) < 0 ? '-0.0' : '0.0';
		}

		$abs = abs($f);
		for ($precision = 0;; $precision++) {
			$s = sprintf("%.{$precision}e", $abs);
			if ((float) $s === $abs || $precision >= 17) {
				break;
			}
		}
		[$mantissa, $exponent] = explode('e', $s);
		$digits = rtrim(str_replace('.', '', $mantissa), '0') ?: '0';
		$e = (int) $exponent;
		$sign = $f < 0 ? '-' : '';
		if ($e >= -4 && $e < 17) {
			if ($e < 0) {
				return $sign . '0.' . str_repeat('0', -$e - 1) . $digits;
			}
			$fraction = (string) substr($digits, $e + 1);
			return $sign . str_pad(substr($digits, 0, $e + 1), $e + 1, '0') . '.' . ($fraction === '' ? '0' : $fraction);
		}
		return $sign . $digits[0] . '.' . (strlen($digits) > 1 ? substr($digits, 1) : '0') . 'e' . ($e < 0 ? '-' : '+') . abs($e);
	}


	/** Prefixes every non-empty line. */
	private static function indent(string $text, string $indentation): string
	{
		return implode("\n", array_map(fn($line) => $line === '' ? $line : $indentation . $line, explode("\n", $text)));
	}
}
