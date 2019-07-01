<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Neon;


/**
 * Simple generator for Nette Object Notation.
 */
final class Encoder
{
	const BLOCK = 1;

	/** @var callable */
	public $replacer;


	/**
	 * Returns the NEON representation of a value.
	 */
	public function encode($var, int $flags = 0, int $depth = 0): string
	{
		if ($this->replacer && !$depth) {
			$key = null;
			call_user_func_array($this->replacer, [&$key, &$var, $depth]);
		}
		$depth++;

		if ($var instanceof \DateTimeInterface) {
			return $var->format('Y-m-d H:i:s O');

		} elseif ($var instanceof Entity) {
			if ($var->value === Neon::CHAIN) {
				return implode('', array_map([$this, 'encode'], $var->attributes));
			}
			return $this->encode($var->value, null, $depth) . '('
				. (is_array($var->attributes) ? substr($this->encode($var->attributes, null, $depth), 1, -1) : '') . ')';
		}

		if (is_object($var)) {
			$obj = $var;
			$var = [];
			foreach ($obj as $k => $v) {
				$var[$k] = $v;
			}
		}

		if (is_array($var)) {
			$isList = !$var || array_keys($var) === range(0, count($var) - 1);
			$s = '';
			if ($flags & self::BLOCK) {
				if (count($var) === 0) {
					return '[]';
				}
				foreach ($var as $k => $v) {
					if ($this->replacer) {
						call_user_func_array($this->replacer, [&$k, &$v, $depth]);
					}
					$v = $this->encode($v, self::BLOCK, $depth);
					$s .= ($isList ? '-' : $this->encode($k, null, $depth) . ':')
						. (strpos($v, "\n") === false
							? ' ' . $v . "\n"
							: "\n" . preg_replace('#^(?=.)#m', "\t", $v) . (substr($v, -2, 1) === "\n" ? '' : "\n"));
				}
				return $s;

			} else {
				foreach ($var as $k => $v) {
					if ($this->replacer) {
						call_user_func_array($this->replacer, [&$k, &$v, $depth]);
					}
					$s .= ($isList ? '' : $this->encode($k, null, $depth) . ': ')
						. $this->encode($v, null, $depth) . ', ';
				}
				return ($isList ? '[' : '{') . substr($s, 0, -2) . ($isList ? ']' : '}');
			}

		} elseif (
			is_string($var)
			&& !is_numeric($var)
			&& !preg_match('~[\x00-\x1F]|^\d{4}|^(true|false|yes|no|on|off|null)\z~i', $var)
			&& preg_match('~^' . Decoder::PATTERNS[1] . '\z~x', $var) // 1 = literals
		) {
			return $var;

		} elseif (is_float($var)) {
			$var = json_encode($var);
			return strpos($var, '.') === false ? $var . '.0' : $var;

		} else {
			return json_encode($var, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		}
	}
}
