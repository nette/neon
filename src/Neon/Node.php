<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Neon;

use function count, is_array;


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


	/** The current position of the first token, computed by a walk from the root. */
	public function getPosition(): ?Position
	{
		return $this->getFirstToken()->getPosition();
	}


	/** The source text of the node, with the trivia of its tokens. */
	public function print(): string
	{
		return implode('', $this->getTokens());
	}


	public function __toString(): string
	{
		return $this->print();
	}


	/** Whether the node spans more than one line, its outer trivia not counted. */
	public function isMultiLine(): bool
	{
		$tokens = $this->getTokens();
		$last = count($tokens) - 1;
		foreach ($tokens as $i => $token) {
			if (($i > 0 && self::hasLineEnding($token->leadingTrivia))
				|| str_contains($token->text, "\n")
				|| ($i < $last && self::hasLineEnding($token->trailingTrivia))
			) {
				return true;
			}
		}
		return false;
	}


	public function hasLeadingComment(): bool
	{
		return (bool) $this->getLeadingComments();
	}


	/**
	 * The comments in the leading trivia of the first token.
	 * @return list<Trivia>
	 */
	public function getLeadingComments(): array
	{
		return array_values(array_filter($this->getFirstToken()->leadingTrivia, fn(Trivia $t) => $t->kind === Trivia::Comment));
	}


	/**
	 * The comments in the trailing trivia of the last token.
	 * @return list<Trivia>
	 */
	public function getTrailingComments(): array
	{
		return array_values(array_filter($this->getLastToken()->trailingTrivia, fn(Trivia $t) => $t->kind === Trivia::Comment));
	}


	/**
	 * The nearest ancestor of the class.
	 * @template T of Node
	 * @param  class-string<T>  $class
	 * @return ?T
	 */
	public function findAncestor(string $class): ?Node
	{
		for ($node = $this->parent; $node; $node = $node->parent) {
			if ($node instanceof $class) {
				return $node;
			}
		}
		return null;
	}


	public function getNextSibling(): ?Node
	{
		$siblings = $this->getSiblings();
		return $siblings[array_search($this, $siblings, true) + 1] ?? null;
	}


	public function getPreviousSibling(): ?Node
	{
		$siblings = $this->getSiblings();
		return $siblings[array_search($this, $siblings, true) - 1] ?? null;
	}


	/**
	 * The descendants of the class in the order of the source.
	 * @template T of Node
	 * @param  class-string<T>  $class
	 * @param  ?callable(T): bool  $filter
	 * @return list<T>
	 */
	public function find(string $class, ?callable $filter = null): array
	{
		$res = [];
		$this->walk(function (Node $node) use ($class, $filter, &$res): bool {
			if ($node instanceof $class && (!$filter || $filter($node))) {
				$res[] = $node;
			}
			return false;
		});
		return $res;
	}


	/**
	 * The first descendant of the class in the order of the source.
	 * @template T of Node
	 * @param  class-string<T>  $class
	 * @param  ?callable(T): bool  $filter
	 * @return ?T
	 */
	public function findFirst(string $class, ?callable $filter = null): ?Node
	{
		$res = null;
		$this->walk(function (Node $node) use ($class, $filter, &$res): bool {
			if ($node instanceof $class && (!$filter || $filter($node))) {
				$res = $node;
				return true;
			}
			return false;
		});
		return $res;
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
	 * Writes the parent as it is and notes in the journal how to undo it.
	 * @internal only the tree writes it: the constructors, the clone, the builder and the editor
	 */
	public function writeParent(?self $parent): void
	{
		if (Journal::isRecording()) {
			$old = $this->parent;
			Journal::record(function () use ($old): void { $this->parent = $old; });
		}
		$this->parent = $parent;
	}


	/**
	 * Writes a slot as it is, without adopting the value or releasing the old one, and notes in the journal how to
	 * undo it.
	 * @internal only the clone, the builder and the editor write through it, the parents seen to
	 */
	public function writeSlot(string $slot, mixed $value): void
	{
		if (Journal::isRecording()) {
			$old = $this->$slot;
			Journal::record(function () use ($slot, $old): void { $this->$slot = $old; });
		}
		$this->$slot = $value;
	}


	/** Makes the node the parent of the children. */
	protected function adopt(Node|Token|null ...$children): void
	{
		foreach ($children as $child) {
			$child?->writeParent($this);
		}
	}


	/** @param  list<Trivia>  $trivia */
	protected static function hasLineEnding(array $trivia): bool
	{
		return array_any($trivia, fn($t) => $t->kind === Trivia::LineEnding);
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


	/** @return list<Node> */
	private function getSiblings(): array
	{
		return $this->parent
			? array_values(array_filter($this->parent->getChildren(), fn($child) => $child instanceof self))
			: [];
	}


	private static function checkChild(mixed $child): Node|Token
	{
		return $child instanceof self || $child instanceof Token
			? $child
			: throw new \LogicException('A slot holds ' . get_debug_type($child) . '.');
	}


	/**
	 * Visits the descendants in the order of the source until the visitor returns true.
	 * @param  \Closure(Node): bool  $visitor
	 */
	private function walk(\Closure $visitor): bool
	{
		return array_any($this->getChildren(), fn($child) => $child instanceof self && ($visitor($child) || $child->walk($visitor)));
	}
}
