<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Neon\Nodes;

use Nette\Neon\Token;
use function count;


/**
 * An array written one item per line; the indentation is read from its items.
 */
final class BlockArrayNode extends ArrayNode
{
	public const Slots = ['items'];


	/**
	 * @internal
	 * @param  non-empty-list<ArrayItemNode>  $items
	 */
	public function __construct(array $items)
	{
		$this->setItems($items);
	}


	public function getChildren(): array
	{
		return $this->items;
	}


	public function getFirstToken(): Token
	{
		return $this->items[0]->getFirstToken();
	}


	public function getLastToken(): Token
	{
		return $this->items[count($this->items) - 1]->getLastToken();
	}
}
