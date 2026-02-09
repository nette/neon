<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Neon;

use function in_array, str_replace, strlen, strrpos, substr, substr_count;


/** @internal */
final class TokenStream
{
	private int $index = 0;


	public function __construct(
		/** @var list<Token> */
		public readonly array $tokens,
	) {
	}


	public function getIndex(): int
	{
		return $this->index;
	}


	public function seek(int $index): void
	{
		$this->index = $index;
	}


	/**
	 * Skips comments and whitespace, then checks whether the next token matches one of the given types.
	 * With no arguments, checks whether any token remains.
	 */
	public function is(int|string ...$types): bool
	{
		while (in_array($this->tokens[$this->index]->type, [Token::Comment, Token::Whitespace], strict: true)) {
			$this->index++;
		}

		return $types
			? in_array($this->tokens[$this->index]->type, $types, strict: true)
			: $this->tokens[$this->index]->type !== Token::End;
	}


	/**
	 * Consumes and returns the next token if it matches one of the given types, or null otherwise.
	 * With no arguments, consumes any next token.
	 */
	public function tryConsume(int|string ...$types): ?Token
	{
		return $this->is(...$types)
			? $this->tokens[$this->index++]
			: null;
	}


	/**
	 * Returns the whitespace indentation of the token at the current position,
	 * i.e. the whitespace token immediately following a newline (or at the start).
	 */
	public function getIndentation(): string
	{
		return in_array($this->tokens[$this->index - 2]->type ?? null, [Token::Newline, null], strict: true)
			&& ($this->tokens[$this->index - 1]->type ?? null) === Token::Whitespace
			? $this->tokens[$this->index - 1]->text
			: '';
	}


	/** Throws a parsing exception with position information from the current or given token. */
	public function error(?string $message = null, ?int $pos = null): never
	{
		$pos ??= $this->index;
		$input = '';
		foreach ($this->tokens as $i => $token) {
			if ($i >= $pos) {
				break;
			}

			$input .= $token->text;
		}

		$line = substr_count($input, "\n") + 1;
		$col = strlen($input) - strrpos("\n" . $input, "\n") + 1;
		$token = $this->tokens[$pos];
		$message ??= 'Unexpected ' . ($token->type === Token::End
			? 'end'
			: "'" . str_replace("\n", '<new line>', substr($this->tokens[$pos]->text, 0, 40)) . "'");
		throw new Exception("$message on line $line at column $col");
	}
}
