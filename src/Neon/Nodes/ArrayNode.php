<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Neon\Nodes;

use Nette\Neon\{Evaluator, Exception, Scalars};
use function is_array;


/**
 * A block or an inline array; its items change only through its methods.
 */
abstract class ArrayNode extends ValueNode
{
	/** @var list<ArrayItemNode> */
	public protected(set) array $items = [];


	/** @return mixed[] */
	public function toValue(): array
	{
		$value = new Evaluator()->evaluate($this);
		return is_array($value) ? $value : throw new \LogicException('An array evaluates to an array.');
	}


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
				?? throw new Exception('Implicit key overflow', $item->getFirstToken()->origin);
			$max = Scalars::maxIntegerKey($max, $key);
			$res[] = $key;
		}
		return $res;
	}


	/** @param  list<ArrayItemNode>  $items */
	protected function setItems(array $items): void
	{
		$this->items = $items;
	}
}
