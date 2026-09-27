<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Neon;

use function count;


/**
 * Texts and positions of a tree; the source text of a node with all its trivia is Node::print().
 */
final class Printer
{
	/** The text of the node without the leading trivia of its first token and the trailing trivia of its last. */
	public static function printText(Node $node): string
	{
		$tokens = $node->getTokens();
		$last = count($tokens) - 1;
		$res = '';
		foreach ($tokens as $i => $token) {
			if ($i > 0) {
				$res .= self::printTrivia($token->leadingTrivia);
			}
			$res .= $token->text;
			if ($i < $last) {
				$res .= self::printTrivia($token->trailingTrivia);
			}
		}
		return $res;
	}


	/**
	 * Current positions of all tokens of the tree in one walk, for an editor asking about many of them.
	 * @return \SplObjectStorage<Token, Position>
	 */
	public static function locateAll(Node $root): \SplObjectStorage
	{
		/** @var \SplObjectStorage<Token, Position> $res */
		$res = new \SplObjectStorage;
		$position = new Position;
		foreach ($root->getTokens() as $token) {
			$position = $position->advance(self::printTrivia($token->leadingTrivia));
			$res[$token] = $position;
			$position = $position->advance($token->text . self::printTrivia($token->trailingTrivia));
		}
		return $res;
	}


	/** @param  list<Trivia>  $trivia */
	private static function printTrivia(array $trivia): string
	{
		$res = '';
		foreach ($trivia as $t) {
			$res .= $t->text;
		}
		return $res;
	}
}
