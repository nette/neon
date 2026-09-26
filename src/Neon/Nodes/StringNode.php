<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Neon\Nodes;

use Nette\Neon\Scalars;
use Nette\Neon\Token;


/**
 * A string in quotes.
 */
final class StringNode extends ValueNode
{
	public const Slots = ['token'];


	/** @internal */
	public function __construct(
		public protected(set) Token $token,
	) {
		$this->adopt($token);
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


	public function toValue(): string
	{
		return Scalars::evaluateString($this->token->text);
	}
}
