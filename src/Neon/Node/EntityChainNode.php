<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Neon\Node;

use Nette\Neon;
use Nette\Neon\Node;


/** @internal */
final class EntityChainNode extends Node
{
	/** @var EntityNode[] */
	public $chain = [];


	public function __construct(array $chain = [], int $startPos = null, int $endPos = null)
	{
		$this->chain = $chain;
		$this->startPos = $startPos;
		$this->endPos = $endPos ?? $startPos;
	}


	public function toValue(): Neon\Entity
	{
		$entities = [];
		foreach ($this->chain as $item) {
			$entities[] = $item->toValue();
		}
		return new Neon\Entity(Neon\Neon::CHAIN, $entities);
	}
}
