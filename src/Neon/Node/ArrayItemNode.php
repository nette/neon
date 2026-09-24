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


	/** The canonical key, or null for an item without a key. */
	public function getKeyValue(): ?string
	{
		$key = $this->key instanceof LiteralNode || $this->key instanceof StringNode ? $this->key->value : null;
		return is_scalar($key) ? (string) $key : null;
	}


	public function &getIterator(): \Generator
	{
		if ($this->key) {
			yield $this->key;
		}
		yield $this->value;
	}
}
