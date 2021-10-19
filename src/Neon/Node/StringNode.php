<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Neon\Node;

use Nette\Neon\Node;


/** @internal */
final class StringNode extends Node
{
	/** @var string */
	public $value;


	public function __construct(string $value, int $pos = null)
	{
		$this->value = $value;
		$this->startPos = $this->endPos = $pos;
	}


	public function toValue(): string
	{
		return $this->value;
	}
}
