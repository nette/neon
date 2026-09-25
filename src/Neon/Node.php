<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Neon;


/**
 * A node of the lossless syntax tree; every token of the source is reachable through the children, and
 * printing the tree gives the source back.
 */
abstract class Node implements \Stringable
{
	/** @var list<string>  the names of the properties holding the children, in the order of the source */
	public const Slots = [];


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
}
