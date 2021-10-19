<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Neon\Node;

use Nette\Neon;
use Nette\Neon\Node;


/** @internal */
final class EntityChainNode extends Node
{
	public function __construct(
		/** @var EntityNode[] */
		public array $chain = [],
		int $startPos = null,
		int $endPos = null,
	) {
		$this->startPos = $startPos;
		$this->endPos = $endPos ?? $startPos;
	}


	public function toValue(callable $evaluator = null): Neon\Entity
	{
		$entities = [];
		foreach ($this->chain as $item) {
			$entities[] = $evaluator ? $evaluator($item) : $item->toValue();
		}
		return new Neon\Entity(Neon\Neon::CHAIN, $entities);
	}


	public function toString(callable $serializer = null): string
	{
		return implode(
			'',
			array_map(fn($entity) => $serializer ? $serializer($entity) : $entity->toString(), $this->chain),
		);
	}


	public function getSubNodes(): array
	{
		$res = [];
		foreach ($this->chain as &$item) {
			$res[] = &$item;
		}
		return $res;
	}
}
