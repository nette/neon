<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Neon\Node;

use Nette\Neon\{Exception, Node, Scalars};


/** @internal */
abstract class ArrayNode extends Node
{
	/** @var list<ArrayItemNode> */
	public array $items = [];


	/**
	 * The key of each item, explicit or implicit: an item without a key gets the greatest integer key so far plus one.
	 * @return list<string>
	 * @throws Exception  for an implicit key beyond the 64-bit range
	 */
	public function getItemKeys(): array
	{
		$max = null;
		$res = [];
		foreach ($this->items as $item) {
			$key = $item->getKeyValue()
				?? Scalars::nextImplicitKey($max)
				?? throw new Exception('Implicit key overflow');
			$max = Scalars::maxIntegerKey($max, $key);
			$res[] = $key;
		}
		return $res;
	}


	public function &getIterator(): \Generator
	{
		foreach ($this->items as &$item) {
			yield $item;
		}
		$this->items = array_values(array_filter($this->items));
	}
}
