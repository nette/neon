<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Neon\Node;

use Nette\Neon\Node;


/** @internal */
final class ArrayItemNode extends Node
{
	/** @var ?Node */
	public $key;

	/** @var Node */
	public $value;


	public function __construct(int $pos = null)
	{
		$this->startPos = $this->endPos = $pos;
	}


	/** @param  self[]  $items */
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


	public function toValue()
	{
		throw new \LogicException;
	}
}
