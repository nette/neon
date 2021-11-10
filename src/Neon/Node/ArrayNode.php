<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Neon\Node;

use Nette\Neon\Node;


/** @internal */
abstract class ArrayNode extends Node
{
	/** @var ArrayItemNode[] */
	public $items = [];


	public function toValue(): array
	{
		return ArrayItemNode::itemsToArray($this->items);
	}


	public function getSubNodes(): array
	{
		$res = [];
		foreach ($this->items as &$item) {
			$res[] = &$item;
		}
		return $res;
	}
}
