<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Neon;


final readonly class Position
{
	public function __construct(
		public int $line = 1,
		public int $column = 1,
		public int $offset = 0,
	) {
	}


	public function __toString(): string
	{
		return "on line $this->line" . ($this->column ? " at column $this->column" : '');
	}
}
