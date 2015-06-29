<?php

/**
 * This file is part of the Nette Framework (http://nette.org)
 * Copyright (c) 2004 David Grudl (http://davidgrudl.com)
 */

namespace Nette\Neon;


/**
 * Representation of 'foo(bar=1)' literal
 */
class Entity extends \stdClass
{
	/** @var mixed */
	public $value;

	/** @var array */
	public $attributes;


	public function __construct($value = NULL, array $attrs = NULL)
	{
		$this->value = $value;
		$this->attributes = (array) $attrs;
	}

}
