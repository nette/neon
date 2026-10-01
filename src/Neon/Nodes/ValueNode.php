<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Neon\Nodes;

use Nette\Neon\Editor;
use Nette\Neon\Evaluator;
use Nette\Neon\Exception;
use Nette\Neon\Journal;
use Nette\Neon\Node;


/**
 * A node that has a value: an array, an entity, a chain, a string or a literal.
 */
abstract class ValueNode extends Node
{
	/**
	 * The PHP value, as Neon::decode() gives it.
	 * @throws Exception  for a tree whose value is not valid
	 */
	public function toValue(): mixed
	{
		return new Evaluator()->evaluate($this);
	}


	/**
	 * Puts the node into the slot of this one, which decides what that means; the node gets the trivia at the edges
	 * of this one in place of its own.
	 * @throws Exception  for a node that cannot stand in the slot
	 */
	public function replaceWith(self $node): void
	{
		Journal::atomic(fn() => Editor::replaceNode($this, $node));
	}


	/** Takes the node out of the tree, with the trivia at its edges; the emptied slot gets null. */
	public function detach(): static
	{
		Journal::atomic(fn() => Editor::detachNode($this));
		return $this;
	}
}
