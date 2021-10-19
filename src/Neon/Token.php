<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Neon;


/** @internal */
final class Token
{
	public const STRING = 1;
	public const LITERAL = 2;
	public const CHAR = 0;
	public const COMMENT = 3;
	public const NEWLINE = 4;
	public const WHITESPACE = 5;


	public function __construct(
		public string $value,
		public int $offset,
		public int|string $type,
	) {
	}
}
