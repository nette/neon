<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Neon;

use function strlen;


/** @internal */
final class TokenStream
{
	private int $pos = 0;


	public function __construct(
		/** @var list<Token> */
		public array $tokens,
	) {
	}


	public function getPos(): int
	{
		return $this->pos;
	}


	public function seek(int $position): void
	{
		$this->pos = $position;
	}


	/** @return list<Token> */
	public function getTokens(): array
	{
		return $this->tokens;
	}


	/**
	 * Skips comments and whitespace, then checks whether the next token matches one of the given types.
	 * With no arguments, checks whether any token remains.
	 */
	public function isNext(int|string ...$types): bool
	{
		while (in_array($this->tokens[$this->pos]->type ?? null, [Token::Comment, Token::Whitespace], true)) {
			$this->pos++;
		}

		return $types
			? in_array($this->tokens[$this->pos]->type ?? null, $types, true)
			: isset($this->tokens[$this->pos]);
	}


	/**
	 * Consumes and returns the next token if it matches one of the given types, or null otherwise.
	 * With no arguments, consumes any next token.
	 */
	public function consume(int|string ...$types): ?Token
	{
		return $this->isNext(...$types)
			? $this->tokens[$this->pos++]
			: null;
	}


	/**
	 * Returns the whitespace indentation of the token at the current or given position,
	 * i.e. the whitespace token immediately following a newline (or at the start).
	 */
	public function getIndentation(?int $pos = null): string
	{
		$pos ??= $this->pos;
		return in_array($this->tokens[$pos - 2]->type ?? null, [Token::Newline, null], true)
			&& ($this->tokens[$pos - 1]->type ?? null) === Token::Whitespace
			? $this->tokens[$pos - 1]->value
			: '';
	}


	/** Throws a parsing exception with position information from the current or given token. */
	public function error(?string $message = null, ?int $pos = null): never
	{
		$pos ??= $this->pos;
		$input = '';
		foreach ($this->tokens as $i => $token) {
			if ($i >= $pos) {
				break;
			}

			$input .= $token->value;
		}

		$line = substr_count($input, "\n") + 1;
		$col = self::charLength(substr($input, strrpos("\n" . $input, "\n"))) + 1;
		$token = $this->tokens[$pos] ?? null;
		if ($message === null) {
			if ($token === null) {
				$message = 'Unexpected end';
			} else {
				preg_match('~.{0,40}~Asu', $token->value, $m); // truncating at a character boundary keeps the message valid UTF-8
				$message = "Unexpected '" . str_replace("\n", '<new line>', $m[0] ?? '') . "'";
			}
		}

		throw new Exception("$message on line $line at column $col");
	}


	/** Counts UTF-8 characters without requiring the mbstring extension; expects valid UTF-8. */
	private static function charLength(string $s): int
	{
		return strlen($s) - (int) preg_match_all('~[\x80-\xBF]~', $s);
	}
}
