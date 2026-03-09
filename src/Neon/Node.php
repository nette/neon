<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Neon;


/**
 * Base class for all AST nodes produced by the NEON parser.
 *
 * @implements \IteratorAggregate<Node>
 */
abstract class Node implements \IteratorAggregate
{
	public ?int $startTokenPos = null;
	public ?int $endTokenPos = null;
	public ?int $startLine = null;
	public ?int $endLine = null;


	/** Converts the node to its PHP value. */
	abstract public function toValue(): mixed;


	/** Converts the node back to its NEON representation. */
	abstract public function toString(): string;


	public function &getIterator(): \Generator
	{
		return;
		yield;
	}
}
