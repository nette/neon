<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Neon;

use Nette\Neon\Nodes\ArrayItemNode;
use Nette\Neon\Nodes\ValueNode;
use function count;


/**
 * Walks the tree depth-first, calling enter() before the children of a node and leave() after them. A visitor
 * returns nothing to go on, a ValueNode to take the place of the visited one, or a TraverseAction. The children
 * of a node are taken when it is entered, so nodes inserted around the visited one later are not visited, and
 * a child taken out of the node meanwhile is skipped; a node the visitor takes out of its place itself is
 * neither walked into nor left. A replacement is visited from its children on. Stop ends the walk at once:
 * no node is entered or left after it. The walk is not one atomic edit: the edits before a failure stay.
 */
final class Traverser
{
	private bool $stopped = false;

	/** @var list<array{Node, ?Node}> the nodes being walked, from the first one, each with the parent it was entered under */
	private array $path = [];


	private function __construct(
		/** @var ?\Closure(Node): (ValueNode|TraverseAction|void|null) */
		private readonly ?\Closure $enter,
		/** @var ?\Closure(Node): (ValueNode|TraverseAction|void|null) */
		private readonly ?\Closure $leave,
	) {
	}


	/**
	 * Returns the node that stands in the place of the visited one afterwards, null if it was removed or taken out.
	 * @param  ?callable(Node): (ValueNode|TraverseAction|void|null)  $enter
	 * @param  ?callable(Node): (ValueNode|TraverseAction|void|null)  $leave
	 * @throws Exception  for a replacement that cannot stand in the slot
	 */
	public static function traverse(Node $node, ?callable $enter = null, ?callable $leave = null): ?Node
	{
		return new self($enter ? $enter(...) : null, $leave ? $leave(...) : null)->visit($node);
	}


	/** @phpstan-impure */
	private function visit(Node $node): ?Node
	{
		$level = count($this->path);
		$this->path[] = [$node, $node->parent];
		try {
			$result = $this->enter ? ($this->enter)($node) : null;
			$current = $this->apply($node, $result);
			if (!$current) {
				return null;
			}
			$this->path[$level][0] = $current;
			if (!$this->isInPlace($level)) {
				return null;
			} elseif ($this->stopped) {
				return $current;
			}

			if ($result !== TraverseAction::SkipChildren) {
				foreach ($current->getChildren() as $child) {
					if ($child instanceof Node && $child->parent === $current) { // tokens are not visited
						$this->visit($child);
						if ($this->stopped) {
							return $current;
						} elseif (!$this->isInPlace($level)) {
							return null;
						}
					}
				}
			}

			$result = $this->leave ? ($this->leave)($current) : null;
			return $this->apply($current, $result);
		} finally {
			array_pop($this->path);
		}
	}


	/** Whether the node at the level and the nodes above it still stand where they were entered. */
	private function isInPlace(int $level): bool
	{
		for ($i = $level; $i >= 0; $i--) {
			[$node, $parent] = $this->path[$i];
			if ($node->parent !== $parent) {
				return false;
			}
		}
		return true;
	}


	private function apply(Node $node, mixed $result): ?Node
	{
		if ($result === TraverseAction::Stop) {
			$this->stopped = true;
		} elseif ($result === TraverseAction::Remove) {
			if (!$node instanceof ArrayItemNode) {
				throw new \LogicException('Only an item can be removed; replace any other node, not ' . $node::class . '.');
			}
			$node->remove();
			return null;
		} elseif ($result instanceof ValueNode) {
			if (!$node instanceof ValueNode) {
				throw new \LogicException('Only a value node can be replaced, not ' . $node::class . '.');
			}
			$node->replaceWith($result);
			return $result;
		} elseif ($result !== null && $result !== TraverseAction::SkipChildren) {
			throw new \LogicException('A visitor of ' . $node::class . ' returned ' . get_debug_type($result) . '; it returns nothing, a ValueNode or a TraverseAction.');
		}
		return $node;
	}
}
