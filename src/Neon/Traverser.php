<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Neon;


/** @internal */
final class Traverser
{
	public const DontTraverseChildren = 1;
	public const StopTraversal = 2;

	/** @var callable(Node): ?int|null */
	private $enter;

	/** @var callable(Node): ?int|null */
	private $leave;

	private bool $stop = false;


	/**
	 * @param  callable(Node): ?int|null  $enter
	 * @param  callable(Node): ?int|null  $leave
	 */
	public function traverse(Node $node, ?callable $enter = null, ?callable $leave = null): void
	{
		$this->enter = $enter;
		$this->leave = $leave;
		$this->stop = false;
		$this->traverseNode($node);
	}


	private function traverseNode(Node $node): void
	{
		$children = true;
		if ($this->enter) {
			$res = ($this->enter)($node);
			if ($res === self::DontTraverseChildren) {
				$children = false;

			} elseif ($res === self::StopTraversal) {
				$this->stop = true;
				$children = false;
			}
		}

		if ($children) {
			foreach ($node->getChildren() as $child) {
				if ($child instanceof Node) {
					$this->traverseNode($child);
					if ($this->stop) {
						break;
					}
				}
			}
		}

		if (!$this->stop && $this->leave && ($this->leave)($node) === self::StopTraversal) {
			$this->stop = true;
		}
	}
}
