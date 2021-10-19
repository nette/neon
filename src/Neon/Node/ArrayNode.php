<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Neon\Node;

use Nette\Neon\Node;


/** @internal */
final class ArrayNode extends Node
{
	/** @var ArrayItemNode[] */
	public array $items = [];


	public function __construct(
		public ?string $indentation = null,
		int $pos = null,
	) {
		$this->startPos = $this->endPos = $pos;
	}


	public function toValue(callable $evaluator = null): array
	{
		return ArrayItemNode::itemsToArray($this->items, $evaluator);
	}


	public function toString(callable $serializer = null): string
	{
		if ($this->indentation === null) {
			$isList = !array_filter($this->items, fn($item) => $item->key);
			$res = ArrayItemNode::itemsToInlineString($this->items, $serializer);
			return ($isList ? '[' : '{') . $res . ($isList ? ']' : '}');

		} elseif (count($this->items) === 0) {
			return '[]';

		} else {
			return ArrayItemNode::itemsToBlockString($this->items, $this->indentation, $serializer);
		}
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
