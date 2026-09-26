<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Neon\Nodes;

use Nette\Neon\{Entity, Evaluator, Token};


/**
 * An entity, `value(attributes)`; without parentheses only as a link of a chain.
 */
final class EntityNode extends ValueNode
{
	public const Slots = ['value', 'attributes'];


	/** @internal */
	public function __construct(
		public protected(set) StringNode|LiteralNode|InlineArrayNode $value,
		public protected(set) ?InlineArrayNode $attributes,
	) {
		$this->adopt($value, $attributes);
	}


	public function getChildren(): array
	{
		return $this->attributes ? [$this->value, $this->attributes] : [$this->value];
	}


	public function getFirstToken(): Token
	{
		return $this->value->getFirstToken();
	}


	public function getLastToken(): Token
	{
		return $this->attributes->closeBracket ?? $this->value->getLastToken();
	}


	public function toValue(): Entity
	{
		$value = new Evaluator()->evaluate($this);
		return $value instanceof Entity ? $value : throw new \LogicException('An entity evaluates to an Entity.');
	}
}
