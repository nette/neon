<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Neon\Node;

use Nette\Neon\Node;


/** @internal */
final class ArrayItemNode extends Node
{
	public ?Node $key = null;
	public Node $value;


	/**
	 * @param  list<self>  $items
	 * @return mixed[]
	 */
	public static function itemsToArray(array $items): array
	{
		$res = [];
		foreach ($items as $item) {
			if ($item->key === null) {
				$res[] = $item->value->toValue();
			} else {
				$res[(string) $item->key->toValue()] = $item->value->toValue();
			}
		}

		return $res;
	}


	/** Use itemsToArray() on the parent array node instead. */
	public function toValue(): never
	{
		throw new \LogicException;
	}


	public function &getIterator(): \Generator
	{
		if ($this->key) {
			yield $this->key;
		}
		yield $this->value;
	}
}
