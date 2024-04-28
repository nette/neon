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
		public array $tokens,
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


	/** @return Token[] */
	public function getTokens(): array
	{
		return $this->tokens;
	}


	public function is(int|string ...$types): bool
	{
		while (in_array($this->tokens[$this->index]->type ?? null, [Token::Comment, Token::Whitespace], strict: true)) {
			$this->index++;
		}

		return $types
			? in_array($this->tokens[$this->index]->type ?? null, $types, strict: true)
			: isset($this->tokens[$this->index]);
	}


	public function tryConsume(int|string ...$types): ?Token
	{
		return $this->is(...$types)
			? $this->tokens[$this->index++]
			: null;
	}


	public function getIndentation(): string
	{
		return in_array($this->tokens[$this->index - 2]->type ?? null, [Token::Newline, null], strict: true)
			&& ($this->tokens[$this->index - 1]->type ?? null) === Token::Whitespace
			? $this->tokens[$this->index - 1]->value
			: '';
	}


	/** @return never */
	public function error(?string $message = null, ?int $pos = null): void
	{
		$pos ??= $this->index;
		$input = '';
		foreach ($this->tokens as $i => $token) {
			if ($i >= $pos) {
				break;
			}

			$input .= $token->value;
		}

		$line = substr_count($input, "\n") + 1;
		$col = strlen($input) - strrpos("\n" . $input, "\n") + 1;
		$token = $this->tokens[$pos] ?? null;
		$message ??= 'Unexpected ' . ($token === null
			? 'end'
			: "'" . str_replace("\n", '<new line>', substr($this->tokens[$pos]->value, 0, 40)) . "'");
		throw new Exception("$message on line $line at column $col");
	}
}
