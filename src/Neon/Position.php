<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Neon;


/**
 * A place in the source: line and column from 1, offset from 0, all counted in Unicode code points.
 */
final readonly class Position
{
	public function __construct(
		public int $line = 1,
		public int $column = 1,
		public int $offset = 0,
	) {
		if ($line < 1 || $column < 1 || $offset < 0) {
			throw new \InvalidArgumentException("Invalid position $line:$column, offset $offset.");
		}
	}


	/** The position after the text, which is valid UTF-8; only a line feed ends a line. */
	public function advance(string $text): self
	{
		$lines = substr_count($text, "\n");
		$length = mb_strlen($text, 'UTF-8');
		return $lines
			? new self($this->line + $lines, mb_strlen(substr($text, strrpos($text, "\n") + 1), 'UTF-8') + 1, $this->offset + $length)
			: new self($this->line, $this->column + $length, $this->offset + $length);
	}


	public function __toString(): string
	{
		return "on line $this->line at column $this->column";
	}
}
