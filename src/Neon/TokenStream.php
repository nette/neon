<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Neon;

use function in_array, str_replace, strlen, strrpos, substr, substr_count;


/** @internal */
final class TokenStream
{
	private int $index = 0;


	public function __construct(
		/** @var Token[] */
		public /*readonly*/ array $tokens,
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
	 * Tells whether the token at current position is of given kind.
	 */
	public function is(int|string ...$kind): bool
	{
		while ($this->tokens[$this->index]->is(Token::Comment, Token::Whitespace)) {
			$this->index++;
		}

		return $kind
			? $this->tokens[$this->index]->is(...$kind)
			: $this->tokens[$this->index]->type !== Token::End;
	}


	/**
	 * Consumes the current token of given kind or returns null.
	 */
	public function tryConsume(int|string ...$kind): ?Token
	{
		return $this->is(...$kind)
			? $this->tokens[$this->index++]
			: null;
	}


	public function getIndentation(): string
	{
		return in_array($this->tokens[$this->index - 2]->type ?? null, [Token::Newline, null], strict: true)
			&& ($this->tokens[$this->index - 1]->type ?? null) === Token::Whitespace
			? $this->tokens[$this->index - 1]->text
			: '';
	}


	/** @return never */
	public function error(?string $message = null, ?int $pos = null): void
	{
		$pos ??= $this->index;
		$token = $this->tokens[$pos];
		$message ??= 'Unexpected ' . ($token->type === Token::End
			? 'end'
			: "'" . str_replace("\n", '<new line>', substr($token->text, 0, 40)) . "'");
		throw new Exception($message, $token->position);
	}
}
