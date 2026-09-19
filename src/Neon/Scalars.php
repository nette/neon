<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Neon;

use function array_key_exists, is_float, strlen;
use const PHP_INT_MAX, PHP_INT_MIN;


/**
 * The one authority on what literals, strings and keys mean, in PHP values: an integer beyond the native range
 * is a string of its digits, a date is DateTimeImmutable, a date that is not valid is no date but a string.
 * @internal
 */
final class Scalars
{
	/** The greatest implicit key and the range of an integer key: 64 bits, whatever the platform. */
	public const IntMax = '9223372036854775807';
	private const SimpleTypes = [
		'true' => true, 'True' => true, 'TRUE' => true, 'yes' => true, 'Yes' => true, 'YES' => true,
		'false' => false, 'False' => false, 'FALSE' => false, 'no' => false, 'No' => false, 'NO' => false,
		'null' => null, 'Null' => null, 'NULL' => null,
	];

	private const PatternInteger = '~^[+-]?\d+$~D';
	private const PatternDecimal = '~^[+-]?(?:\d+(?:\.\d*)?|\.\d+)(?:[eE][+-]?\d+)?$~D';
	private const PatternBase = '~^0(?:x[0-9a-fA-F]+|o[0-7]+|b[01]+)$~D';
	private const PatternDate = '~^(\d{4})-(\d\d?)-(\d\d?)(?:(?:[Tt]| +)(\d\d?):(\d\d):(\d\d)(?:\.(\d*))? *(Z|[-+]\d\d?(?::?\d\d)?)?)?$~D';

	private const Escapes = [
		't' => "\t", 'n' => "\n", 'r' => "\r", 'f' => "\f", 'b' => "\x08", '"' => '"', '\\' => '\\', '/' => '/', '_' => "\u{A0}",
	];


	/** The value of a literal that is not a key. */
	public static function evaluateLiteral(string $text): bool|\DateTimeImmutable|float|int|string|null
	{
		return array_key_exists($text, self::SimpleTypes)
			? self::SimpleTypes[$text]
			: self::evaluateNumber($text) ?? self::evaluateDate($text) ?? $text;
	}


	/** A number literal as int, float, or a string of digits beyond the native range; null if the text is no number. */
	public static function evaluateNumber(string $text): int|float|string|null
	{
		if (preg_match(self::PatternInteger, $text)) {
			return self::toInteger(ltrim($text, '+'));
		} elseif (preg_match(self::PatternBase, $text)) {
			return self::toInteger(self::toDecimal(substr($text, 2), ['x' => 16, 'o' => 8, 'b' => 2][$text[1]]));
		} elseif (preg_match(self::PatternDecimal, $text)) {
			return (float) $text;
		}
		return null;
	}


	/** The canonical key of a literal written as a key. */
	public static function literalKey(string $text): string
	{
		$number = self::evaluateNumber($text);
		return match (true) {
			$number === null => $text,
			is_float($number) => self::floatKey($number),
			default => (string) $number,
		};
	}


	/** Whether a canonical key is an integer key: what PHP converts to an integer array key. */
	public static function isIntegerKey(string $key): bool
	{
		return (bool) preg_match('~^(?:0|-?[1-9]\d*)$~D', $key) && self::compareIntegers($key, self::IntMax) <= 0 && self::compareIntegers($key, '-9223372036854775808') >= 0;
	}


	/** The implicit key after the greatest integer key so far, null for none; null when it would leave the range. */
	public static function nextImplicitKey(?string $max): ?string
	{
		return match ($max) {
			null => '0',
			self::IntMax => null,
			default => (string) ((int) $max + 1),
		};
	}


	/** The greatest integer key so far, after the key. */
	public static function maxIntegerKey(?string $max, string $key): ?string
	{
		return self::isIntegerKey($key) && ($max === null || self::compareIntegers($key, $max) > 0) ? $key : $max;
	}


	/**
	 * The value of a string token.
	 * @throws Exception  for an invalid escape sequence
	 */
	public static function evaluateString(string $text): string
	{
		$text = str_replace("\r", '', $text);
		if (preg_match('~^(\'\'\'|""")\n~', $text)) {
			$indent = preg_match('~^...\n++([\t ]*)~', $text, $m) ? $m[1] : '';
			$res = substr($text, 3, -3);
			if ($indent !== '') {
				$res = str_replace("\n" . $indent, "\n", $res);
			}
			$res = preg_replace('~^\n|\n[\t ]*+$~D', '', $res);
		} else {
			$res = substr($text, 1, -1);
			if ($text[0] === "'") {
				$res = str_replace("''", "'", $res);
			}
		}

		if ($text[0] === "'") {
			return $res;
		}

		// case-insensitive, so \U0041 and \uD801\UDC01 are one sequence each
		return preg_replace_callback('~\\\(?:ud[89ab][0-9a-f]{2}\\\ud[c-f][0-9a-f]{2}|u[0-9a-f]{4}|.)~iu', function (array $m): string {
			$seq = $m[0];
			$c = $seq[1];
			if (isset(self::Escapes[$c])) {
				return self::Escapes[$c];
			} elseif ($c !== 'u' || strlen($seq) < 6) {
				throw new Exception("Invalid escaping sequence $seq");
			} elseif (strlen($seq) === 12) {
				if ($seq[7] !== 'u') {
					throw new Exception("Invalid UTF-8 sequence $seq");
				}
				$code = ((hexdec(substr($seq, 2, 4)) - 0xD800) << 10) + (hexdec(substr($seq, 8)) - 0xDC00) + 0x10000;
				return mb_chr((int) $code, 'UTF-8');
			}
			$code = (int) hexdec(substr($seq, 2));
			if ($code >= 0xD800 && $code <= 0xDFFF) {
				throw new Exception("Invalid UTF-8 sequence $seq");
			}
			return mb_chr($code, 'UTF-8');
		}, $res);
	}


	/** The date of a literal; null if the text is no date or its components make no valid date and time. */
	private static function evaluateDate(string $text): ?\DateTimeImmutable
	{
		if (!preg_match(self::PatternDate, $text, $m, PREG_UNMATCHED_AS_NULL)) {
			return null;
		}
		[, $year, $month, $day, $hour, $minute, $second, , $zone] = $m;
		if (!checkdate((int) $month, (int) $day, (int) $year ?: 2000) // checkdate() takes years from 1; 0 is a leap year as 2000 is
			|| ($hour !== null && ($hour > 23 || $minute > 59 || $second > 59))
			|| ($zone !== null && $zone !== 'Z' && preg_match('~^.(\d\d?):?(\d\d)?$~', $zone, $z) && ((int) ($z[2] ?? 0) > 59 || (int) $z[1] * 60 + (int) ($z[2] ?? 0) > 14 * 60))
		) {
			return null;
		}

		try {
			$date = new \DateTimeImmutable($text); // as written, so the time zone is the one 3.x gives
		} catch (\Throwable) {
			return null;
		}
		$errors = \DateTimeImmutable::getLastErrors();
		return $errors && ($errors['warning_count'] || $errors['error_count']) ? null : $date;
	}


	/** A float as a key: 14 significant digits, as PHP converts it to a string, whatever the `precision`. */
	private static function floatKey(float $f): string
	{
		if (is_nan($f)) {
			return 'NAN';
		} elseif (is_infinite($f)) {
			return $f > 0 ? 'INF' : '-INF';
		} elseif ($f === 0.0) {
			return fdiv(1, $f) < 0 ? '-0' : '0';
		}

		[$mantissa, $exponent] = explode('e', sprintf('%.13e', $f));
		$e = (int) $exponent;
		if ($e >= -4 && $e < 14) {
			$digits = number_format($f, max(0, 13 - $e), '.', '');
			return str_contains($digits, '.') ? rtrim(rtrim($digits, '0'), '.') : $digits;
		}
		$m = rtrim(rtrim($mantissa, '0'), '.');
		return (str_contains($m, '.') ? $m : $m . '.0') . 'E' . ($e < 0 ? '-' : '+') . abs($e);
	}


	/** Decimal digits as int if they fit the native integer, else as the canonical string. */
	private static function toInteger(string $digits): int|string
	{
		$negative = $digits[0] === '-';
		$digits = ltrim(ltrim($digits, '-'), '0') ?: '0';
		$signed = $negative && $digits !== '0' ? '-' . $digits : $digits;
		return self::compareIntegers($signed, (string) PHP_INT_MAX) <= 0 && self::compareIntegers($signed, (string) PHP_INT_MIN) >= 0
			? (int) $signed
			: $signed;
	}


	/** Converts digits of a base to decimal digits of any length, without bcmath. */
	private static function toDecimal(string $digits, int $base): string
	{
		$decimal = [0]; // little-endian decimal digits
		foreach (str_split(strtolower($digits)) as $char) {
			$carry = (int) hexdec($char);
			foreach ($decimal as $i => $d) {
				$carry += $d * $base;
				$decimal[$i] = $carry % 10;
				$carry = intdiv($carry, 10);
			}
			for (; $carry; $carry = intdiv($carry, 10)) {
				$decimal[] = $carry % 10;
			}
		}
		return implode('', array_reverse($decimal));
	}


	/** Compares two canonical decimal integers of any length. */
	private static function compareIntegers(string $a, string $b): int
	{
		$na = $a[0] === '-';
		$nb = $b[0] === '-';
		if ($na !== $nb) {
			return $na ? -1 : 1;
		}
		$res = strlen($a) <=> strlen($b) ?: strcmp($a, $b) <=> 0;
		return $na ? -$res : $res;
	}
}
