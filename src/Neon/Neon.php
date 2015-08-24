<?php

/**
 * This file is part of the Nette Framework (http://nette.org)
 * Copyright (c) 2004 David Grudl (http://davidgrudl.com)
 */

declare(strict_types = 1);

namespace Nette\Neon;


/**
 * Simple parser & generator for Nette Object Notation.
 */
class Neon
{
	const BLOCK = Encoder::BLOCK;
	const CHAIN = '!!chain';


	/**
	 * Returns the NEON representation of a value.
	 * @param  mixed
	 * @param  int
	 * @return string
	 */
	public static function encode($var, int $options = NULL): string
	{
		$encoder = new Encoder;
		return $encoder->encode($var, $options);
	}


	/**
	 * Decodes a NEON string.
	 * @param  string
	 * @return mixed
	 */
	public static function decode(string $input)
	{
		$decoder = new Decoder;
		return $decoder->decode($input);
	}

}
