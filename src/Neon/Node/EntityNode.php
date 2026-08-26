<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Neon\Node;

use Nette\Neon\Entity;
use Nette\Neon\Exception;
use Nette\Neon\Neon;
use Nette\Neon\Node;


/** @internal */
final class EntityNode extends Node
{
	public function __construct(
		public Node $value,
		/** @var list<ArrayItemNode> */
		public array $attributes = [],
	) {
	}


	public function toValue(): Entity
	{
		$value = $this->value->toValue();
		if ($value === Neon::Chain) { // must not be forgeable from input
			throw new Exception("Entity name '" . Neon::Chain . "' is reserved.");
		}

		return new Entity($value, ArrayItemNode::itemsToArray($this->attributes));
	}


	public function toString(): string
	{
		return $this->value->toString()
			. '('
			. ($this->attributes ? ArrayItemNode::itemsToInlineString($this->attributes) : '')
			. ')';
	}


	public function &getIterator(): \Generator
	{
		yield $this->value;

		foreach ($this->attributes as &$item) {
			yield $item;
		}
		$this->attributes = array_values(array_filter($this->attributes));
	}
}
