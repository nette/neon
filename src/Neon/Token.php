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
	/** @var string */
	public $value;

	/** @var int */
	public $offset;


	public function __construct(string $value, int $offset)
	{
		$this->value = $value;
		$this->offset = $offset;
	}
}
