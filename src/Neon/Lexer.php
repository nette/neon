<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Neon;

use function array_keys, count, implode, preg_match, preg_match_all, str_replace, strlen, substr;
use const PREG_SET_ORDER;


/** @internal */
final class Lexer
{
	public const Patterns = [
		// strings
		Token::String => <<<'XX'
			'''\n (?:(?: [^\n] | \n(?![\t ]*+''') )*+ \n)?[\t ]*+''' |
			"""\n (?:(?: [^\n] | \n(?![\t ]*+""") )*+ \n)?[\t ]*+""" |
			' (?: '' | [^'\n] )*+ ' |
			" (?: \\. | [^"\\\n] )*+ "
			XX,

		// literal / boolean / integer / float
		Token::Literal => <<<'XX'
			(?: [^#"',:=[\]{}()\n\t `-] | (?<!["']) [:-] [^"',=[\]{}()\n\t ] )
			(?:
				[^,:=\]})(\n\t ]++ |
				:(?! [\n\t ,\]})] | $ ) |
				[ \t]++ [^#,:=\]})(\n\t ]
			)*+
			XX,

		// punctuation
		Token::Char => '[,:=[\]{}()-]',

		// comment
		Token::Comment => '\#.*+',

		// new line
		Token::Newline => '\n++',

		// whitespace
		Token::Whitespace => '[\t ]++',
	];


	public function tokenize(string $input): TokenStream
	{
		$input = str_replace("\r", '', $input);
		$pattern = '~(' . implode(')|(', self::Patterns) . ')~Amixu';
		$res = preg_match_all($pattern, $input, $matches, PREG_SET_ORDER);
		if ($res === false) {
			throw new Exception('Invalid UTF-8 sequence.');
		}

		$types = array_keys(self::Patterns);
		$position = new Position;

		$tokens = [];
		foreach ($matches as $match) {
			$type = $types[count($match) - 2];
			$tokens[] = new Token($type === Token::Char ? $match[0] : $type, $match[0], $position);
			$position = $this->advance($position, $match[0]);
		}

		$tokens[] = new Token(Token::End, '', $position);

		$stream = new TokenStream($tokens);
		if ($position->offset !== strlen($input)) {
			$s = str_replace("\n", '\n', substr($input, $position->offset, 40));
			throw new Exception("Unexpected '$s'", $position);
		}

		return $stream;
	}


	private function advance(Position $position, string $str): Position
	{
		if ($lines = substr_count($str, "\n")) {
			return new Position(
				$position->line + $lines,
				strlen($str) - strrpos($str, "\n"),
				$position->offset + strlen($str),
			);
		} else {
			return new Position(
				$position->line,
				$position->column + strlen($str),
				$position->offset + strlen($str),
			);
		}
	}


	public static function requiresDelimiters(string $s): bool
	{
		return preg_match('~[\x00-\x1F]|^[+-.]?\d|^(true|false|yes|no|on|off|null)$~Di', $s)
			|| !preg_match('~^' . self::Patterns[Token::Literal] . '$~Dx', $s);
	}
}
