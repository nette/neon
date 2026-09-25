<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Neon\Nodes;

use Nette\Neon\{Entity, Evaluator, Token};
use function count;


/**
 * A chain of entities, `first(a)second(b)`.
 */
final class EntityChainNode extends ValueNode
{
	public const Slots = ['entities'];


	/** @internal */
	public function __construct(
		/** @var non-empty-list<EntityNode> */
		public protected(set) array $entities,
	) {
	}


	public function getChildren(): array
	{
		return $this->entities;
	}


	public function getFirstToken(): Token
	{
		return $this->entities[0]->getFirstToken();
	}


	public function getLastToken(): Token
	{
		return $this->entities[count($this->entities) - 1]->getLastToken();
	}


	public function toValue(): Entity
	{
		$value = new Evaluator()->evaluate($this);
		return $value instanceof Entity ? $value : throw new \LogicException('A chain evaluates to an Entity.');
	}
}
