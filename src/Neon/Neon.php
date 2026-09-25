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
	/** The value of an entity that represents a chain; its attributes are the links. */
	public const Chain = '!!chain';

	#[\Deprecated('use Neon::Chain')]
	public const CHAIN = self::Chain;

	#[\Deprecated('use Neon::encode(..., blockMode: true)')]
	public const BLOCK = true;


	/**
	 * Writes a PHP value as NEON, inline or as blocks. A closure $blockMode(value, keys on the way) is asked for every
	 * array and writes it as a block if true.
	 * @param  bool|\Closure(mixed, list<string>): bool  $blockMode
	 * @throws Exception  for a value that cannot be written as NEON
	 */
	public static function encode(mixed $value, bool|\Closure $blockMode = false, string $indentation = "\t"): string
	{
		return new Encoder($blockMode, $indentation)->encode($value);
	}


	/**
	 * Parses NEON and returns the corresponding PHP value.
	 * @throws Exception  for input that is not NEON
	 */
	public static function decode(string $input): mixed
	{
		return new Evaluator()->evaluate(self::parse($input)->value);
	}


	/**
	 * Parses a NEON file and returns the corresponding PHP value.
	 * @throws Exception  for a file that cannot be read or is not NEON
	 */
	public static function decodeFile(string $file): mixed
	{
		return self::decode(self::readFile($file));
	}


	/**
	 * Parses NEON into the lossless syntax tree.
	 * @throws Exception  for input that is not NEON
	 */
	public static function parse(string $input): Nodes\DocumentNode
	{
		return new Parser()->parse($input);
	}


	/**
	 * Parses a NEON file into the lossless syntax tree.
	 * @throws Exception  for a file that cannot be read or is not NEON
	 */
	public static function parseFile(string $file): Nodes\DocumentNode
	{
		return self::parse(self::readFile($file));
	}


	private static function readFile(string $file): string
	{
		$input = @file_get_contents($file); // @ is escalated to exception
		if ($input === false) {
			$error = preg_replace('#^\w+\(.*?\): #', '', error_get_last()['message'] ?? '');
			throw new Exception("Unable to read file '$file'. $error");
		}
		return $input;
	}
}
