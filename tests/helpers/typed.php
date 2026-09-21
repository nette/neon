<?php declare(strict_types=1);

/**
 * Typed values of the corpus and their PHP projection: integers beyond the native range as strings of digits,
 * an empty map as an empty array, a date as DateTimeImmutable of its text.
 */

namespace Tests;

use Nette\Neon\Entity;
use Nette\Neon\Neon;


final class Typed
{
	/** @param  array<string, mixed>  $typed */
	public static function toPhp(array $typed): mixed
	{
		return match ($typed['type'] ?? null) {
			'null' => null,
			'bool' => self::check($typed['value'], is_bool(...)),
			'int' => self::toInteger(self::check($typed['value'], fn($v) => is_string($v) && preg_match('~^-?\d+$~D', $v))),
			'float' => match ($value = self::check($typed['value'], is_string(...))) {
				'INF' => INF,
				'-INF' => -INF,
				'NAN' => NAN,
				default => (float) $value,
			},
			'string' => self::check($typed['value'], is_string(...)),
			'date' => new \DateTimeImmutable(self::check($typed['value'], is_string(...))),
			'entity' => new Entity(self::toPhp($typed['value']), self::toPhp($typed['attributes'])),
			'chain' => new Entity(Neon::Chain, array_map(self::toPhp(...), $typed['entities'])),
			'list' => array_map(self::toPhp(...), self::check($typed['items'], array_is_list(...))),
			'map' => self::mapToPhp($typed['entries']),
			default => throw new \LogicException('Unknown type ' . json_encode($typed['type'] ?? null)),
		};
	}


	/**
	 * The value to encode: as toPhp(), but an empty map is an empty object, which the encoder writes as {}.
	 * @param  array<string, mixed>  $typed
	 */
	public static function toEncodable(array $typed): mixed
	{
		return match ($typed['type'] ?? null) {
			'map' => $typed['entries'] ? array_map(fn($value) => self::toEncodable($value), self::mapToPhp($typed['entries'], raw: true)) : new \stdClass,
			'list' => array_map(self::toEncodable(...), self::check($typed['items'], array_is_list(...))),
			'entity' => new Entity(self::toEncodable($typed['value']), self::toEncodable($typed['attributes'])),
			'chain' => new Entity(Neon::Chain, array_map(self::toEncodable(...), $typed['entities'])),
			default => self::toPhp($typed),
		};
	}


	/** Equality of PHP values: a NaN equals a NaN, dates by their components and offset, the order of keys counts. */
	public static function same(mixed $a, mixed $b): bool
	{
		return match (true) {
			is_float($a) && is_float($b) => $a === $b || (is_nan($a) && is_nan($b)),
			$a instanceof \DateTimeImmutable && $b instanceof \DateTimeImmutable => $a->format('Y-m-d H:i:s.u P') === $b->format('Y-m-d H:i:s.u P'),
			$a instanceof Entity && $b instanceof Entity => self::same($a->value, $b->value) && self::same($a->attributes, $b->attributes),
			is_array($a) && is_array($b) => array_keys($a) === array_keys($b) && array_all($a, fn($v, $k) => self::same($v, $b[$k])),
			default => $a === $b,
		};
	}


	/** @param  list<array{string, array<string, mixed>}>  $entries */
	private static function mapToPhp(array $entries, bool $raw = false): array
	{
		$res = [];
		foreach ($entries as [$key, $value]) {
			$res[$key] = $raw ? $value : self::toPhp($value);
		}
		if ($res && array_is_list($res)) {
			throw new \LogicException('A map with keys 0..n-1 must be written as a list.');
		}
		return $res;
	}


	private static function toInteger(string $digits): int|string
	{
		$int = (int) $digits;
		return (string) $int === $digits ? $int : $digits;
	}


	private static function check(mixed $value, callable $check): mixed
	{
		return $check($value) ? $value : throw new \LogicException('Invalid typed value ' . json_encode($value));
	}
}
