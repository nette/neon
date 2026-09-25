<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Neon\Nodes;

use Nette\Neon\Scalars;
use Nette\Neon\Token;


/**
 * A value written without quotes: a word, a number, a date, or a string.
 */
final class LiteralNode extends ValueNode
{
	public const Slots = ['token'];


	/** @internal */
	public function __construct(
		public protected(set) Token $token,
	) {
	}


	public function getChildren(): array
	{
		return [$this->token];
	}


	public function getFirstToken(): Token
	{
		return $this->token;
	}


	public function getLastToken(): Token
	{
		return $this->token;
	}


	public function toValue(): bool|\DateTimeImmutable|float|int|string|null
	{
		return Scalars::evaluateLiteral($this->token->text);
	}
}
