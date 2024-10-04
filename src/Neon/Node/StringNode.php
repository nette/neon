<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Neon\Node;

use Nette;
use Nette\Neon\Node;


/** @internal */
final class StringNode extends Node
{
	private const EscapeSequences = [
		't' => "\t", 'n' => "\n", 'r' => "\r", 'f' => "\x0C", 'b' => "\x08", '"' => '"', '\\' => '\\', '/' => '/', '_' => "\u{A0}",
	];

	/** @var string */
	public $value;


	public function __construct(string $value)
	{
		$this->value = $value;
	}


	public function toValue(): string
	{
		return $this->value;
	}


	public static function parse(string $s): string
	{
		if (preg_match('#^...\n++([\t ]*+)#', $s, $m)) { // multiline
			$res = substr($s, 3, -3);
			$res = str_replace("\n" . $m[1], "\n", $res);
			$res = preg_replace('#^\n|\n[\t ]*+$#D', '', $res);
		} else {
			$res = substr($s, 1, -1);
			if ($s[0] === "'") {
				$res = str_replace("''", "'", $res);
			}
		}

		if ($s[0] === "'") {
			return $res;
		}

		return preg_replace_callback(
			'#\\\\(?:ud[89ab][0-9a-f]{2}\\\\ud[c-f][0-9a-f]{2}|u[0-9a-f]{4}|x[0-9a-f]{2}|.)#i',
			function (array $m): string {
				$sq = $m[0];
				if (isset(self::EscapeSequences[$sq[1]])) {
					return self::EscapeSequences[$sq[1]];
				} elseif ($sq[1] === 'u' && strlen($sq) >= 6) {
					if (($res = json_decode('"' . $sq . '"')) !== null) {
						return $res;
					}

					throw new Nette\Neon\Exception("Invalid UTF-8 sequence $sq");
				} elseif ($sq[1] === 'x' && strlen($sq) === 4) {
					trigger_error("Neon: '$sq' is deprecated, use '\\uXXXX' instead.", E_USER_DEPRECATED);
					return chr(hexdec(substr($sq, 2)));
				} else {
					throw new Nette\Neon\Exception("Invalid escaping sequence $sq");
				}
			},
			$res
		);
	}


	public function toString(): string
	{
		if (strpos($this->value, "\n") === false) {
			return preg_match('~[\x00-\x08\x0B-\x1F]~', $this->value)
				? json_encode($this->value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
				: "'" . str_replace("'", "''", $this->value) . "'";

		} elseif (preg_match('~[\x00-\x08\x0B-\x1F]|\n[\t ]+\'{3}~', $this->value)) {
			$s = substr(json_encode($this->value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 1, -1);
			$s = preg_replace_callback(
				'#[^\\\\]|\\\\(.)#s',
				function ($m) {
					return ['n' => "\n", 't' => "\t", '"' => '"'][$m[1] ?? ''] ?? $m[0];
				},
				$s
			);
			$s = str_replace('"""', '""\"', $s);
			$delim = '"""';

		} else {
			$s = $this->value;
			$delim = "'''";
		}

		$s = preg_replace('#^(?=.)#m', "\t", $s);
		return $delim . "\n" . $s . "\n" . $delim;
	}
}
