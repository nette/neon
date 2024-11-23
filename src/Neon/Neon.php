<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Neon;


/**
 * Facade for parsing and encoding the NEON format.
 * @see https://neon.nette.org
 */
final class Neon
{
	public const Chain = '!!chain';

	#[\Deprecated('use Neon::Chain')]
	public const CHAIN = self::Chain;

	/** @deprecated use parameter $blockMode */
	public const BLOCK = true;


	/**
	 * Encodes a PHP value to a NEON string.
	 */
	public static function encode(mixed $value, bool $blockMode = false, string $indentation = "\t"): string
	{
		$encoder = new Encoder;
		$encoder->blockMode = $blockMode;
		$encoder->indentation = $indentation;
		return $encoder->encode($value);
	}


	/**
	 * Parses a NEON string and returns the corresponding PHP value.
	 */
	public static function decode(string $input): mixed
	{
		$decoder = new Decoder;
		return $decoder->decode($input);
	}


	/**
	 * Parses a NEON file and returns the corresponding PHP value. Strips the UTF-8 BOM if present.
	 */
	public static function decodeFile(string $file): mixed
	{
		$input = @file_get_contents($file); // @ is escalated to exception
		if ($input === false) {
			$error = preg_replace('#^\w+\(.*?\): #', '', error_get_last()['message'] ?? '');
			throw new Exception("Unable to read file '$file'. $error");
		}

		if (str_starts_with($input, "\u{FEFF}")) { // BOM
			$input = substr($input, 3);
		}

		return self::decode($input);
	}
}
