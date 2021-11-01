<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Neon;


/** @internal */
final class Traverser
{
	/** @var callable(Node): ?Node */
	private $callback;


	/** @param  callable(Node): ?Node  $callback */
	public function traverse(Node $node, callable $callback): Node
	{
		$this->callback = $callback;
		return $this->traverseNode($node);
	}


	private function traverseNode(Node $node): Node
	{
		$node = ($this->callback)($node) ?? $node;
		foreach ($node->getSubNodes() as &$subnode) {
			$subnode = $this->traverseNode($subnode);
		}
		return $node;
	}
}
