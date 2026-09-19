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


	public function toString(): string
	{
		if ($this->value instanceof \DateTimeInterface) {
			return $this->value->format('Y-m-d H:i:s O');

		} elseif (is_string($this->value)) {
			return $this->value;

		} elseif (is_float($this->value)) {
			if (!is_finite($this->value)) {
				throw new Exception('INF and NAN cannot be encoded to NEON');
			}
			$res = json_encode($this->value);
			return str_contains($res, '.') ? $res : $res . '.0';

		} elseif (is_int($this->value) || is_bool($this->value) || $this->value === null) {
			return json_encode($this->value);

		} else {
			throw new \LogicException;
		}
	}
}
