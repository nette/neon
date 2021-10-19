<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Neon;


/**
 * Converts value to NEON format.
 * @internal
 */
final class Encoder
{
	public const BLOCK = 1;


	/**
	 * Returns the NEON representation of a value.
	 */
	public function encode($val, int $flags = 0): string
	{
		$node = $this->valueToNode($val, (bool) ($flags & self::BLOCK));
		return $node->toString();
	}


	public function valueToNode($val, bool $blockMode = false): Node
	{
		if ($val instanceof \DateTimeInterface) {
			return new Node\LiteralNode($val);

		} elseif ($val instanceof Entity && $val->value === Neon::CHAIN) {
			$node = new Node\EntityChainNode;
			foreach ($val->attributes as $entity) {
				$node->chain[] = $this->valueToNode($entity, $blockMode);
			}
			return $node;

		} elseif ($val instanceof Entity) {
			return new Node\EntityNode(
				$this->valueToNode($val->value),
				$this->arrayToNodes((array) $val->attributes)
			);

		} elseif (is_object($val) || is_array($val)) {
			$node = new Node\ArrayNode($blockMode ? '' : null);
			$node->items = $this->arrayToNodes($val, $blockMode);
			return $node;

		} elseif (is_string($val) && Lexer::requiresDelimiters($val)) {
			return new Node\StringNode($val);

		} else {
			return new Node\LiteralNode($val);
		}
	}


	private function arrayToNodes($val, bool $blockMode = false): array
	{
		$res = [];
		$counter = 0;
		$hide = true;
		foreach ($val as $k => $v) {
			$res[] = $item = new Node\ArrayItemNode;
			$item->key = $hide && $k === $counter ? null : self::valueToNode($k);
			$item->value = self::valueToNode($v, $blockMode);
			if ($hide && is_int($k)) {
				$hide = $k === $counter;
				$counter = max($k + 1, $counter);
			}
		}
		return $res;
	}
}
