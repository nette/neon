<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Neon;


/**
 * Simple parser & generator for Nette Object Notation.
 * @see https://ne-on.org
 */
final class Neon
{
	public const BLOCK = Encoder::BLOCK;
	public const Chain = '!!chain';
	public const CHAIN = self::Chain;


	/**
	 * Returns value converted to NEON.
	 */
	public static function encode($value, bool $blockMode = false, string $indentation = "\t"): string
	{
		$encoder = new Encoder;
		$encoder->blockMode = $blockMode;
		$encoder->indentation = $indentation;
		return $encoder->encode($value);
	}


	/**
	 * Converts given NEON to PHP value.
	 * @return mixed
	 */
	public static function decode(string $input)
	{
		$decoder = new Decoder;
		return $decoder->decode($input);
	}


	/**
	 * Converts given NEON file to PHP value.
	 * @return mixed
	 */
	public static function decodeFile(string $file)
	{
		$input = @file_get_contents($file); // @ is escalated to exception
		if ($input === false) {
			$error = preg_replace('#^\w+\(.*?\): #', '', error_get_last()['message'] ?? '');
			throw new Exception("Unable to read file '$file'. $error");
		}

		if (substr($input, 0, 3) === "\u{FEFF}") { // BOM
			$input = substr($input, 3);
		}

		return self::decode($input);
	}
}
