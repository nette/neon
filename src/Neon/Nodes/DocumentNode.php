<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Neon\Nodes;

use Nette\Neon\{Builder, Editor, Evaluator, Exception, Journal, Node, Token};


/**
 * The root of the tree: a value, a block or nothing, and the end of the file with the trivia after the value.
 */
final class DocumentNode extends Node
{
	public const Slots = ['value', 'endOfFile'];


	/** @internal */
	public function __construct(
		public protected(set) ?ValueNode $value,
		public protected(set) Token $endOfFile,
	) {
		$this->adopt($value, $endOfFile);
	}


	public function getChildren(): array
	{
		return $this->value ? [$this->value, $this->endOfFile] : [$this->endOfFile];
	}


	public function getFirstToken(): Token
	{
		return $this->value?->getFirstToken() ?? $this->endOfFile;
	}


	public function getLastToken(): Token
	{
		return $this->endOfFile;
	}


	/**
	 * The PHP value of the document, as Neon::decode() gives it.
	 * @throws Exception  for a tree whose value is not valid
	 */
	public function toValue(): mixed
	{
		return new Evaluator()->evaluate($this->value);
	}


	/**
	 * The item at the path of keys, the same keys decode() gives; a path continues into the attributes of an entity.
	 * A segment [item => n] is the n-th item of the array.
	 * @param  list<string|int|array{item: int}>  $path
	 */
	public function findItem(array $path): ?ArrayItemNode
	{
		try {
			return $path ? Editor::resolvePath($this, $path) : null;
		} catch (Exception) {
			return null;
		}
	}


	/**
	 * Writes the value at the path, a value of PHP or a node; a missing last key is added, unless create is false.
	 * @param  list<string|int|array{item: int}>  $path
	 * @throws Exception  for a path that is not there or a value that cannot stand there
	 */
	public function setValue(array $path, mixed $value, bool $create = true): void
	{
		$value = Builder::materialize($value); // iterators run before the journal records
		Journal::atomic(fn() => Editor::setByPath($this, $path, $value, $create));
	}


	/**
	 * Adds an item to the array at the path, without a key unless given one, at the end or before the item at
	 * the position.
	 * @param  list<string|int|array{item: int}>  $path
	 * @throws Exception  for a path that is not there or a key that is there already
	 */
	public function addItem(array $path, mixed $value, ?string $key = null, ?int $position = null): ArrayItemNode
	{
		$value = Builder::materialize($value); // iterators run before the journal records
		return Journal::atomic(fn() => Editor::addByPath($this, $path, $key, $value, $position));
	}


	/**
	 * Renames the key of the item at the path.
	 * @param  list<string|int|array{item: int}>  $path
	 * @throws Exception  for a path that is not there or a key that is there already
	 */
	public function setKey(array $path, string $key): void
	{
		Journal::atomic(fn() => Editor::setItemKey(Editor::resolvePath($this, $path), $key));
	}
}
