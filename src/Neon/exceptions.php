<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Neon;


/**
 * The input is not valid NEON. The message joins the description from the catalog of the specification with
 * the position, `... on line X at column Y`.
 */
class Exception extends \Exception
{
	public function __construct(
		private readonly string $description,
		public readonly ?Position $position = null,
		?\Throwable $previous = null,
	) {
		parent::__construct($position ? "$description $position" : $description, 0, $previous);
	}


	/** The message without the position, as the catalog of the specification writes it. */
	public function getDescription(): string
	{
		return $this->description;
	}
}
