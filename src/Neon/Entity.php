<?php

/**
 * This file is part of the Nette Framework (http://nette.org)
 * Copyright (c) 2004 David Grudl (http://davidgrudl.com)
 */

namespace Nette\Neon;

use Nette;


/**
 * Representation of 'foo(bar=1)' literal
 */
class Entity extends \stdClass
{
	public $value;
	public $attributes;
}
