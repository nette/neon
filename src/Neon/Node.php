<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Neon;

use function is_array;


/**
 * A node of the lossless syntax tree; every token of the source is reachable through the children, and
 * printing the tree gives the source back.
 */
abstract class Node implements \Stringable
{
	/** @var list<string>  the names of the properties holding the children, in the order of the source */
	public const Slots = [];

	/** The node this one belongs to; only the tree writes it. */
	public private(set) ?Node $parent = null;


	/**
	 * The children in the order of the source, without the empty slots.
	 * @return list<Node|Token>
	 */
	abstract public function getChildren(): array;


	/** @return list<Token> */
	public function getTokens(): array
	{
		$res = [];
		$this->collectTokens($res);
		return $res;
	}


	abstract public function getFirstToken(): Token;


	abstract public function getLastToken(): Token;


	/** The source text of the node, with the trivia of its tokens. */
	public function print(): string
	{
		return implode('', $this->getTokens());
	}


	public function __toString(): string
	{
		return $this->print();
	}


	/** A deep copy without a parent: every node and token of it is new, the trivia are shared. */
	public function __clone()
	{
		$this->parent = null;
		foreach (static::Slots as $slot) {
			$child = $this->$slot;
			if (is_array($child)) {
				$copy = [];
				foreach ($child as $node) {
					$copy[] = $node = clone self::checkChild($node);
					$node->writeParent($this);
				}
			} elseif ($child !== null) {
				$copy = clone self::checkChild($child);
				$copy->writeParent($this);
			} else {
				continue;
			}
			$this->writeSlot($slot, $copy);
		}
	}


	/** @return never */
	public function __serialize(): array
	{
		throw new \LogicException('A node cannot be serialized; serialize the text of the document instead.');
	}


	/**
	 * Writes the parent as it is.
	 * @internal only the tree writes it: the constructors, the clone, the builder and the editor
	 */
	public function writeParent(?self $parent): void
	{
		$this->parent = $parent;
	}


	/**
	 * Writes a slot as it is, without adopting the value or releasing the old one.
	 * @internal only the clone, the builder and the editor write through it, the parents seen to
	 */
	public function writeSlot(string $slot, mixed $value): void
	{
		$this->$slot = $value;
	}


	/** Makes the node the parent of the children. */
	protected function adopt(Node|Token|null ...$children): void
	{
		foreach ($children as $child) {
			$child?->writeParent($this);
		}
	}


	/** @param  list<Token>  $res */
	private function collectTokens(array &$res): void
	{
		foreach ($this->getChildren() as $child) {
			if ($child instanceof Token) {
				$res[] = $child;
			} else {
				$child->collectTokens($res);
			}
		}
	}


	private static function checkChild(mixed $child): Node|Token
	{
		return $child instanceof self || $child instanceof Token
			? $child
			: throw new \LogicException('A slot holds ' . get_debug_type($child) . '.');
	}
}
