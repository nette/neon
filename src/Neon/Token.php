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
	public const String = 1;
	public const Literal = 2;
	public const Char = 0;
	public const Comment = 3;
	public const Newline = 4;
	public const Whitespace = 5;
	public const End = -1;


	public function __construct(
		public int|string $type,
		public string $text,
		public Position $position,
	) {
	}


	public function is(int|string ...$kind): bool
	{
		return in_array($this->type, $kind, strict: true);
	}
}
