<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Neon\Nodes;

use Nette\Neon\Token;


/**
 * An array in brackets `[]`, braces `{}` or parentheses `()`, the attributes of an entity.
 */
final class InlineArrayNode extends ArrayNode
{
	public const Slots = ['openBracket', 'items', 'closeBracket'];


	/**
	 * @internal
	 * @param  list<ArrayItemNode>  $items
	 */
	public function __construct(
		public protected(set) Token $openBracket,
		array $items,
		public protected(set) Token $closeBracket,
	) {
		$this->adopt($openBracket, $closeBracket);
		$this->setItems($items);
	}


	public function getChildren(): array
	{
		return [$this->openBracket, ...$this->items, $this->closeBracket];
	}


	public function getFirstToken(): Token
	{
		return $this->openBracket;
	}


	public function getLastToken(): Token
	{
		return $this->closeBracket;
	}
}
