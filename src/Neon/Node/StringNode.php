<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Neon\Node;

use Nette\Neon\Node;
use const JSON_UNESCAPED_SLASHES, JSON_UNESCAPED_UNICODE;


/** @internal */
final class StringNode extends Node
{
	public function __construct(
		public string $value,
		public string $indentation = "\t",
	) {
	}


	public function toValue(): string
	{
		return $this->value;
	}


	public function toString(): string
	{
		if (!str_contains($this->value, "\n")) {
			return preg_match('~[\x00-\x08\x0B-\x1F]~', $this->value)
				? json_encode($this->value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
				: "'" . str_replace("'", "''", $this->value) . "'";

		} elseif (preg_match('~[\x00-\x08\x0B-\x1F]|\n[\t ]*+\'{3}|^\n*+[\t ]~', $this->value)) {
			// ''' at line start would collide with the closing delimiter after indentation is added,
			// whitespace starting the first line would be read as indentation
			$s = substr(json_encode($this->value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 1, -1);
			$s = preg_replace_callback(
				'#[^\\\]|\\\(.)#s',
				fn($m) => ['n' => "\n", 't' => "\t", '"' => '"'][$m[1] ?? ''] ?? $m[0],
				$s,
			);
			$s = str_replace('"""', '""\"', $s);
			$s = preg_replace_callback('#^(\n*+)([\t ]++)#', fn($m) => $m[1] . strtr($m[2], [' ' => '\u0020', "\t" => '\t']), $s);
			$delim = '"""';

		} else {
			$s = $this->value;
			$delim = "'''";
		}

		$s = preg_replace('#^(?=.)#m', $this->indentation, $s);
		return $delim . "\n" . $s . "\n" . $delim;
	}
}
