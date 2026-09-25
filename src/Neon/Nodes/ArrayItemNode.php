<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Neon\Nodes;

use Nette\Neon\{Node, Scalars, Token};


/**
 * An item of an array: `- value` and `key: value` in a block, `value` and `key: value` inline, where
 * the separator is the comma after the item.
 */
final class ArrayItemNode extends Node
{
	public const Slots = ['bullet', 'key', 'colon', 'value', 'separator'];


	/** @internal */
	public function __construct(
		public protected(set) ?Token $bullet,
		public protected(set) StringNode|LiteralNode|null $key,
		public protected(set) ?Token $colon,
		public protected(set) ?ValueNode $value,
		public protected(set) ?Token $separator = null,
	) {
	}


	public function getChildren(): array
	{
		$res = [];
		foreach ([$this->bullet, $this->key, $this->colon, $this->value, $this->separator] as $child) {
			if ($child) {
				$res[] = $child;
			}
		}
		return $res;
	}


	public function getFirstToken(): Token
	{
		return $this->bullet ?? $this->key?->getFirstToken() ?? $this->value?->getFirstToken() ?? throw new \LogicException;
	}


	public function getLastToken(): Token
	{
		return $this->separator ?? $this->value?->getLastToken() ?? $this->colon ?? $this->bullet ?? throw new \LogicException;
	}


	/** The canonical key, or null for an item without a key. */
	public function getKeyValue(): ?string
	{
		return match (true) {
			$this->key instanceof StringNode => Scalars::evaluateString($this->key->token->text),
			$this->key instanceof LiteralNode => Scalars::literalKey($this->key->token->text),
			default => null,
		};
	}
}
