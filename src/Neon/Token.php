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

	/** @var string */
	public $value;

	/** @var int */
	public $offset;

	/** @var int|string */
	public $type;


	public function __construct(string $value, int $offset, $type)
	{
		$this->value = $value;
		$this->offset = $offset;
		$this->type = $type;
	}
}
