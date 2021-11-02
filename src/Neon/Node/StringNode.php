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
	private const ESCAPE_SEQUENCES = [
		't' => "\t", 'n' => "\n", 'r' => "\r", 'f' => "\x0C", 'b' => "\x08", '"' => '"', '\\' => '\\', '/' => '/', '_' => "\u{A0}",
	];


	public function __construct(
		public string $value,
		int $pos = null,
	) {
		$this->startPos = $this->endPos = $pos;
	}


	public function toValue(callable $evaluator = null): string
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
			'#\\\\(?:ud[89ab][0-9a-f]{2}\\\\ud[c-f][0-9a-f]{2}|u[0-9a-f]{4}|.)#i',
			function (array $m): string {
				$sq = $m[0];
				if (isset(self::ESCAPE_SEQUENCES[$sq[1]])) {
					return self::ESCAPE_SEQUENCES[$sq[1]];
				} elseif ($sq[1] === 'u' && strlen($sq) >= 6) {
					return json_decode('"' . $sq . '"') ?? throw new Nette\Neon\Exception("Invalid UTF-8 sequence $sq");
				} else {
					throw new Nette\Neon\Exception("Invalid escaping sequence $sq");
				}
			},
			$res,
		);
	}


	public function toString(callable $serializer = null): string
	{
		$res = json_encode($this->value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		if ($res === false) {
			throw new Nette\Neon\Exception('Invalid UTF-8 sequence: ' . $this->value);
		}
		if (str_contains($this->value, "\n")) {
			$res = preg_replace_callback(
				'#[^\\\\]|\\\\(.)#s',
				fn($m) => ['n' => "\n\t", 't' => "\t", '"' => '"'][$m[1] ?? ''] ?? $m[0],
				$res,
			);
			$res = '"""' . "\n\t" . substr($res, 1, -1) . "\n" . '"""';
		}
		return $res;
	}
}
