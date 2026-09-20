<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Neon\Node;

use Nette\Neon\Exception;
use Nette\Neon\Node;
use function is_bool, is_float, is_int, is_string;


/** @internal */
final class LiteralNode extends Node
{
	public function __construct(
		/** @var  bool|int|float|string|\DateTimeInterface|null */
		public mixed $value,
	) {
	}


	/** @return bool|int|float|string|\DateTimeInterface|null */
	public function toValue(): mixed
	{
		return $this->value;
	}
}
