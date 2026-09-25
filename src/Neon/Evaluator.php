<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Neon;

use function is_int, is_string;


/**
 * Builds the PHP value of a tree. A subclass changes how literals, strings, entities, chains and items become values;
 * the keys and the order of an array stay those of the document. The tree is never changed.
 */
class Evaluator
{
	/**
	 * @throws Exception  for a tree whose value is not valid
	 */
	final public function evaluate(?Nodes\ValueNode $node, Place $place = Place::Value): mixed
	{
		return match (true) {
			$node === null => null,
			$node instanceof Nodes\LiteralNode => $this->evaluateLiteral($node, $place),
			$node instanceof Nodes\StringNode => $this->evaluateString($node, $place),
			$node instanceof Nodes\EntityNode => $this->evaluateEntity($node, $place),
			$node instanceof Nodes\EntityChainNode => $this->evaluateChain($node),
			$node instanceof Nodes\ArrayNode => $this->evaluateArray($node),
			default => throw new \LogicException('Unknown node ' . $node::class),
		};
	}


	/** The value of a literal; as a key, its canonical key. */
	protected function evaluateLiteral(Nodes\LiteralNode $node, Place $place): mixed
	{
		return $place === Place::Key
			? Scalars::literalKey($node->token->text)
			: Scalars::evaluateLiteral($node->token->text);
	}


	protected function evaluateString(Nodes\StringNode $node, Place $place): mixed
	{
		return Scalars::evaluateString($node->token->text);
	}


	/** An entity standing as a value, or as a link of a chain. */
	protected function evaluateEntity(Nodes\EntityNode $node, Place $place): mixed
	{
		return new Entity(
			$this->evaluate($node->value, Place::EntityHead),
			$node->attributes ? $this->evaluateArray($node->attributes) : [],
		);
	}


	/** A chain of entities; its links are evaluated as entities in the place of a link. */
	protected function evaluateChain(Nodes\EntityChainNode $node): mixed
	{
		return new Entity(
			Neon::Chain,
			array_map(fn(Nodes\EntityNode $link) => $this->evaluateEntity($link, Place::ChainLink), $node->entities),
		);
	}


	/** The value of an item of an array, null for an item without one. */
	protected function evaluateItem(Nodes\ArrayItemNode $item): mixed
	{
		return $this->evaluate($item->value);
	}


	/**
	 * The array of the items under their keys, explicit or implicit; a later item of the same key replaces the earlier.
	 * @return mixed[]
	 * @throws Exception  when two different keys of the document project to one
	 */
	final protected function evaluateArray(Nodes\ArrayNode $node): array
	{
		$res = $origins = [];
		$keys = $node->getItemKeys();
		foreach ($node->items as $i => $item) {
			$key = $item->key ? $this->evaluate($item->key, Place::Key) : $keys[$i];
			if (!is_int($key) && !is_string($key)) {
				throw new \LogicException('The key of an item must be int or string, ' . get_debug_type($key) . ' given.');
			} elseif (($origins[$key] ?? $keys[$i]) !== $keys[$i]) {
				throw new Exception("Duplicated key '$key'", $item->getFirstToken()->origin);
			}
			$origins[$key] = $keys[$i];
			$res[$key] = $this->evaluateItem($item);
		}
		return $res;
	}
}
