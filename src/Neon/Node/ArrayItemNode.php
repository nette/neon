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
	public ?Node $key = null;
	public Node $value;


	/**
	 * @param  self[]  $items
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


	/** @param  self[]  $items */
	public static function itemsToInlineString(array $items, string $indentation): string
	{
		$res = '';
		foreach ($items as $item) {
			$res .= ($res === '' ? '' : ', ')
				. ($item->key ? $item->key->toString($indentation) . ': ' : '')
				. $item->value->toString($indentation);
		}

		return $res;
	}


	/** @param  self[]  $items */
	public static function itemsToBlockString(array $items, string $indentation): string
	{
		$res = '';
		foreach ($items as $item) {
			$v = $item->value->toString($indentation);
			$res .= ($item->key ? $item->key->toString($indentation) . ':' : '-')
				. ($item->value instanceof BlockArrayNode && $item->value->items
					? "\n" . $v . (substr($v, -2, 1) === "\n" ? '' : "\n")
					: ' ' . $v . "\n");
		}

		return $res;
	}


	public function toValue(): mixed
	{
		throw new \LogicException;
	}


	public function toString(string $indentation): string
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
