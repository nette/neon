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
	public const DontTraverseChildren = 1;
	public const StopTraversal = 2;

	/** @var callable(Node): (Node|int|null) */
	private $callback;

	/** @var bool */
	private $stop;


	/** @param  callable(Node): (Node|int|null)  $callback */
	public function traverse(Node $node, callable $callback): Node
	{
		$this->callback = $callback;
		$this->stop = false;
		return $this->traverseNode($node);
	}


	private function traverseNode(Node $node): Node
	{
		$children = true;
		if ($this->callback) {
			$res = ($this->callback)($node);
			if ($res instanceof Node) {
				$node = $res;

			} elseif ($res === self::DontTraverseChildren) {
				$children = false;

			} elseif ($res === self::StopTraversal) {
				$this->stop = true;
				$children = false;
			}
		}

		if ($children) {
			foreach ($node->getSubNodes() as &$subnode) {
				$subnode = $this->traverseNode($subnode);
				if ($this->stop) {
					break;
				}
			}
		}

		return $node;
	}
}
