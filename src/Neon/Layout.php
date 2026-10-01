<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Neon;

use Nette\Neon\Nodes\ArrayItemNode;
use Nette\Neon\Nodes\BlockArrayNode;
use Nette\Neon\Nodes\DocumentNode;
use function count, strlen;


/**
 * The layout of new text, derived from the neighbourhood of the place of an edit.
 * @internal
 */
final class Layout
{
	/** The difference of indentation between a nested block and its parent. */
	public static function getUnit(Node $at): string
	{
		foreach (self::findCandidates($at) as $candidate) {
			if ($unit = self::getUnitOf($candidate)) {
				return $unit;
			}
		}
		return "\t";
	}


	/**
	 * The indentation of the block the item is in. It is the indentation of the line of the item, except
	 * for the first item of a block opened on the line of a dash, which stands after the dash.
	 */
	public static function getIndentation(ArrayItemNode $item): string
	{
		$block = $item->parent;
		$owner = $block?->parent;
		if (!$block instanceof BlockArrayNode
			|| !$owner instanceof ArrayItemNode
			|| !$owner->bullet
			|| self::hasLineEnding($owner->bullet->trailingTrivia)
		) {
			return $item->getIndentation();
		} elseif ($second = $block->items[1] ?? null) {
			return $second->getIndentation();
		}

		// as the parser decides: a tab if the next line within the block starts with one
		$outer = self::getIndentation($owner);
		$nextIndent = '';
		foreach (array_slice($block->getTokens(), 1) as $token) {
			if (array_any($token->leadingTrivia, fn(Trivia $t) => $t->kind === Trivia::Whitespace)) {
				$nextIndent = $token->leadingTrivia[count($token->leadingTrivia) - 1]->text;
				break;
			}
		}
		return $outer . (str_starts_with($nextIndent, $outer . "\t") ? "\t" : '  ');
	}


	/** The line ending of the nearest preceding token, else the first of the document, else LF. */
	public static function getLineEnding(Node|Token $at): string
	{
		$root = self::getRoot($at);
		if (!$root) {
			return "\n";
		}
		$first = $root->getFirstToken();
		return self::findLineEnding(($at instanceof Node ? $at->getFirstToken() : $at)->iterateNeighbours(backward: true))
			?? self::findLineEnding([$first])
			?? self::findLineEnding($first->iterateNeighbours(backward: false))
			?? "\n";
	}


	/** The document the node or token is in. */
	public static function getRoot(Node|Token $at): ?DocumentNode
	{
		$node = $at instanceof DocumentNode ? $at : $at->parent;
		while ($node?->parent) {
			$node = $node->parent;
		}
		return $node instanceof DocumentNode ? $node : null;
	}


	/**
	 * The blocks to take the unit from: the siblings, the nearest first, then the ancestors, then the whole document.
	 * @return \Generator<BlockArrayNode>
	 */
	private static function findCandidates(Node $at): \Generator
	{
		$block = $at instanceof BlockArrayNode ? $at : $at->findAncestor(BlockArrayNode::class);
		if ($block) {
			$item = $at instanceof ArrayItemNode ? $at : $at->findAncestor(ArrayItemNode::class);
			$index = $item ? max((int) array_search($item, $block->items, true), 0) : 0;
			for ($distance = 0; $distance < count($block->items); $distance++) {
				foreach ($distance ? [$index - $distance, $index + $distance] : [$index] as $i) {
					$value = $block->items[$i]->value ?? null;
					if ($value instanceof BlockArrayNode) {
						yield $value;
					}
				}
			}
		}
		for ($node = $block; $node; $node = $node->parent) {
			if ($node instanceof BlockArrayNode) {
				yield $node;
			}
		}
		if ($root = self::getRoot($at)) {
			yield from $root->find(BlockArrayNode::class);
		}
	}


	/** The unit of a nested block, or null for a block opened on the line of a dash or not nested. */
	private static function getUnitOf(BlockArrayNode $block): ?string
	{
		$owner = $block->parent;
		$head = $owner instanceof ArrayItemNode ? $owner->colon ?? $owner->bullet : null;
		if (!$owner instanceof ArrayItemNode || !$head || !self::hasLineEnding($head->trailingTrivia)) {
			return null; // not nested, or opened on the line of the dash
		}
		$outer = $owner->getIndentation();
		$inner = $block->items[0]->getIndentation();
		return strlen($inner) > strlen($outer) && str_starts_with($inner, $outer) ? substr($inner, strlen($outer)) : null;
	}


	/**
	 * The first line ending in the trivia of the tokens.
	 * @param  iterable<Token>  $tokens
	 */
	private static function findLineEnding(iterable $tokens): ?string
	{
		foreach ($tokens as $token) {
			foreach ([...$token->trailingTrivia, ...$token->leadingTrivia] as $trivia) {
				if ($trivia->kind === Trivia::LineEnding) {
					return $trivia->text;
				}
			}
		}
		return null;
	}


	/** @param  list<Trivia>  $trivia */
	private static function hasLineEnding(array $trivia): bool
	{
		return array_any($trivia, fn(Trivia $t) => $t->kind === Trivia::LineEnding);
	}
}
