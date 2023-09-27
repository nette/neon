<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Neon;


/**
 * The exception that indicates error of NEON processing.
 */
class Exception extends \Exception
{
	public ?Position /*readonly*/ $position = null;


	public function __construct(string $message, ?Position $position = null, ?\Throwable $previous = null)
	{
		$message .= $position ? ' ' . $position : '';
		$this->position = $position;
		parent::__construct($message, 0, $previous);
	}
}
