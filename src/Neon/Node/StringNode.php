<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Neon\Node;

use Nette\Neon\Node;


/** @internal */
final class StringNode extends Node
{
	public function __construct(
		public string $value,
	) {
	}
}
