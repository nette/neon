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


	public function __construct(int $pos = null)
	{
		$this->startPos = $this->endPos = $pos;
	}


	/**
	 * @param  self[]  $items
	 * @return mixed[]
	 */
	public static function itemsToArray(array $items, callable $evaluator = null): array
	{
		$res = [];
		foreach ($items as $item) {
			$v = $evaluator ? $evaluator($item->value) : $item->value->toValue();
			if ($item->key === null) {
				$res[] = $v;
			} else {
				$res[(string) ($evaluator ? $evaluator($item->key) : $item->key->toValue())] = $v;
			}
		}
		return $res;
	}


	/** @param  self[]  $items */
	public static function itemsToInlineString(array $items, callable $serializer = null): string
	{
		$res = '';
		foreach ($items as $item) {
			$res .= ($res === '' ? '' : ', ')
				. ($item->key ? ($serializer ? $serializer($item->key) : $item->key->toString()) . ': ' : '')
				. ($serializer ? $serializer($item->value) : $item->value->toString());
		}
		return $res;
	}


	/** @param  self[]  $items */
	public static function itemsToBlockString(array $items, string $indentation, callable $serializer = null): string
	{
		$res = '';
		foreach ($items as $item) {
			$v = $serializer ? $serializer($item->value) : $item->value->toString();
			$res .= ($item->key ? ($serializer ? $serializer($item->key) : $item->key->toString()) . ':' : '-')
				. (str_contains($v, "\n")
					? "\n" . preg_replace('#^(?=.)#m', $indentation, $v) . (substr($v, -2, 1) === "\n" ? '' : "\n")
					: ' ' . $v . "\n");
		}
		return $res;
	}


	public function toValue(callable $evaluator = null): mixed
	{
		throw new \LogicException;
	}


	public function toString(callable $serializer = null): string
	{
		throw new \LogicException;
	}


	public function getSubNodes(): array
	{
		return $this->key ? [&$this->key, &$this->value] : [&$this->value];
	}
}
