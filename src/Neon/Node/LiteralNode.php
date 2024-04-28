<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Neon\Node;

use Nette\Neon\Exception;
use Nette\Neon\Node;


/** @internal */
final class LiteralNode extends Node
{
	private const SimpleTypes = [
		'true' => true, 'True' => true, 'TRUE' => true, 'yes' => true, 'Yes' => true, 'YES' => true,
		'false' => false, 'False' => false, 'FALSE' => false, 'no' => false, 'No' => false, 'NO' => false,
		'null' => null, 'Null' => null, 'NULL' => null,
	];

	private const PatternDatetime = '#\d\d\d\d-\d\d?-\d\d?(?:(?:[Tt]| ++)\d\d?:\d\d:\d\d(?:\.\d*+)? *+(?:Z|[-+]\d\d?(?::?\d\d)?)?)?$#DA';
	private const PatternHex = '#0x[0-9a-fA-F]++$#DA';
	private const PatternOctal = '#0o[0-7]++$#DA';
	private const PatternBinary = '#0b[0-1]++$#DA';


	public function __construct(
		public mixed $value,
	) {
	}


	public function toValue(): mixed
	{
		return $this->value;
	}


	public static function parse(string $value, bool $isKey = false): mixed
	{
		if (!$isKey && array_key_exists($value, self::SimpleTypes)) {
			return self::SimpleTypes[$value];

		} elseif (is_numeric($value)) {
			return is_int($num = $value * 1) || preg_match('#[.eE]#', $value) ? $num : $value;

		} elseif (preg_match(self::PatternHex, $value)) {
			return self::baseConvert(substr($value, 2), 16);

		} elseif (preg_match(self::PatternOctal, $value)) {
			return self::baseConvert(substr($value, 2), 8);

		} elseif (preg_match(self::PatternBinary, $value)) {
			return self::baseConvert(substr($value, 2), 2);

		} elseif (!$isKey && preg_match(self::PatternDatetime, $value)) {
			return new \DateTimeImmutable($value);

		} else {
			return $value;
		}
	}


	public static function baseConvert(string $number, int $base): string|int
	{
		if (strlen($number) < 16) {
			$res = base_convert($number, $base, 10);
		} elseif (!extension_loaded('bcmath')) {
			throw new Exception("The number '$number' is too large, enable 'bcmath' extension to handle it.");
		} else {
			$res = '0';
			for ($i = 0; $i < strlen($number); $i++) {
				$char = $number[$i];
				$char = match (true) {
					$char >= 'a' => ord($char) - 87,
					$char >= 'A' => ord($char) - 55,
					default => $char,
				};
				$res = bcmul($res, (string) $base, 0);
				$res = bcadd($res, (string) $char, 0);
			}
		}

		return is_int($num = $res * 1) ? $num : $res;
	}


	public function toString(): string
	{
		if ($this->value instanceof \DateTimeInterface) {
			return $this->value->format('Y-m-d H:i:s O');

		} elseif (is_string($this->value)) {
			return $this->value;

		} elseif (is_float($this->value)) {
			if (!is_finite($this->value)) {
				throw new Exception('INF and NAN cannot be encoded to NEON');
			}
			$res = json_encode($this->value);
			return str_contains($res, '.') ? $res : $res . '.0';

		} elseif (is_int($this->value) || is_bool($this->value) || $this->value === null) {

			return json_encode($this->value);

		} else {
			throw new \LogicException;
		}
	}
}
