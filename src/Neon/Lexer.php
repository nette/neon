<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Neon;


/** @internal */
final class Lexer
{
	public const PATTERNS = [
		// strings
		'
			\'\'\'\n (?:(?: [^\n] | \n(?![\t\ ]*+\'\'\') )*+ \n)?[\t\ ]*+\'\'\' |
			"""\n (?:(?: [^\n] | \n(?![\t\ ]*+""") )*+ \n)?[\t\ ]*+""" |
			\' (?: \'\' | [^\'\n] )*+ \' |
			" (?: \\\\. | [^"\\\\\n] )*+ "
		',

		// literal / boolean / integer / float
		'
			(?: [^#"\',:=[\]{}()\n\t\ `-] | (?<!["\']) [:-] [^"\',=[\]{}()\n\t\ ] )
			(?:
				[^,:=\]})(\n\t\ ]++ |
				:(?! [\n\t\ ,\]})] | $ ) |
				[\ \t]++ [^#,:=\]})(\n\t\ ]
			)*+
		',

		// punctuation
		'[,:=[\]{}()-]',

		// comment
		'?:\#.*+',

		// new line + indent
		'\n[\t\ ]*+',

		// whitespace
		'?:[\t\ ]++',
	];


	/** @return Token[] */
	public function tokenize(string $input, &$error): array
	{
		$pattern = '~(' . implode(')|(', self::PATTERNS) . ')~Amixu';
		$tokens = preg_split($pattern, $input, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_OFFSET_CAPTURE | PREG_SPLIT_DELIM_CAPTURE);
		if ($tokens === false) {
			throw new Exception('Invalid UTF-8 sequence.');
		}

		foreach ($tokens as &$token) {
			$token = new Token($token[0], $token[1]);
		}

		$error = $tokens && !preg_match($pattern, end($tokens)->value);
		return $tokens;
	}


	public static function requiresDelimiters(string $s): bool
	{
		return preg_match('~[\x00-\x1F]|^[+-.]?\d|^(true|false|yes|no|on|off|null)$~Di', $s)
			|| !preg_match('~^' . self::PATTERNS[1] . '$~Dx', $s);
	}
}
