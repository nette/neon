<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Neon\Node;


/** @internal */
final class InlineArrayNode extends ArrayNode
{
	public function __construct(int $pos = null)
	{
		$this->startPos = $this->endPos = $pos;
	}


	public function toString(): string
	{
		$isList = !array_filter($this->items, function ($item) { return $item->key; });
		$res = ArrayItemNode::itemsToInlineString($this->items);
		return ($isList ? '[' : '{') . $res . ($isList ? ']' : '}');
	}
}
