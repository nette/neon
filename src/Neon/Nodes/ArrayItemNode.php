<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Neon\Nodes;

use Nette\Neon\{Builder, CommentPolicy, Editor, Exception, Journal, Node, Scalars, Token, Trivia};


/**
 * An item of an array: `- value` and `key: value` in a block, `value` and `key: value` inline, where
 * the separator is the comma after the item.
 */
final class ArrayItemNode extends Node
{
	public const Slots = ['bullet', 'key', 'colon', 'value', 'separator'];

	/** Whether removing the last item of the value leaves null instead of an empty collection. */
	public bool $nullable = false;


	/** @internal */
	public function __construct(
		public protected(set) ?Token $bullet,
		public protected(set) StringNode|LiteralNode|null $key,
		public protected(set) ?Token $colon,
		public protected(set) ?ValueNode $value,
		public protected(set) ?Token $separator = null,
	) {
		$this->adopt($bullet, $key, $colon, $value, $separator);
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


	/** The value of the item, null without one. */
	public function getValue(): mixed
	{
		return $this->value?->toValue();
	}


	/**
	 * Writes the value: a value of PHP or a node. Writing an equal value changes nothing; a new scalar keeps the
	 * style of the old one where it can.
	 * @throws Exception  for a value that cannot stand in the item
	 */
	public function setValue(mixed $value): void
	{
		$value = Builder::materialize($value); // iterators run before the journal records
		Journal::atomic(fn() => Editor::setItemValue($this, $value));
	}


	/**
	 * Renames the key.
	 * @throws Exception  for an item without a key or a key that is there already
	 */
	public function setKey(string $key): void
	{
		Journal::atomic(fn() => Editor::setItemKey($this, $key));
	}


	/**
	 * Removes the item from its array, with the comments it owns.
	 * @throws Exception  for an item that is not in an array
	 */
	public function remove(CommentPolicy $comments = CommentPolicy::Drop, bool $mergeBlankLines = false): void
	{
		Journal::atomic(fn() => Editor::removeItem($this, $comments, null, $mergeBlankLines));
	}


	/** The indentation of the line the item starts on. */
	public function getIndentation(): string
	{
		$leading = $this->getFirstToken()->leadingTrivia;
		$last = end($leading);
		return $last && $last->kind === Trivia::Whitespace ? $last->text : '';
	}
}
