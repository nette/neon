<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Neon\Node;

use Nette\Neon\Node;


/** @internal */
final class EntityChainNode extends Node
{
	public function __construct(
		/** @var list<EntityNode> */
		public array $entities = [],
	) {
	}


	public function &getIterator(): \Generator
	{
		foreach ($this->entities as &$item) {
			yield $item;
		}
		$this->entities = array_values(array_filter($this->entities));
	}
}
