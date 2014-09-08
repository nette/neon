<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Neon;


/**
 * Converts value to NEON format.
 * @internal
 */
final class Encoder
{
	public const BLOCK = 1;

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
			return $this->encode($var->value, 0, $depth) . '('
				. (is_array($var->attributes) ? substr($this->encode($var->attributes, 0, $depth), 1, -1) : '') . ')';
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
					$s .= ($isList ? '-' : $this->encode($k, 0, $depth) . ':')
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
					$s .= ($isList ? '' : $this->encode($k, 0, $depth) . ': ')
						. $this->encode($v, 0, $depth) . ', ';
				}
				return ($isList ? '[' : '{') . substr($s, 0, -2) . ($isList ? ']' : '}');
			}

		} elseif (is_string($var)) {
			if (!preg_match('~[\x00-\x1F]|^[+-.]?\d|^(true|false|yes|no|on|off|null)$~Di', $var)
				&& preg_match('~^' . Decoder::PATTERNS[1] . '$~Dx', $var) // 1 = literals
			) {
				return $var;
			}

			$res = json_encode($var, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
			if ($res === false) {
				throw new Exception('Invalid UTF-8 sequence: ' . $var);
			}
			if (strpos($var, "\n") !== false) {
				$res = preg_replace_callback('#[^\\\\]|\\\\(.)#s', function ($m) {
					return ['n' => "\n\t", 't' => "\t", '"' => '"'][$m[1] ?? ''] ?? $m[0];
				}, $res);
				$res = '"""' . "\n\t" . substr($res, 1, -1) . "\n" . '"""';
			}
			return $res;

		} elseif (is_float($var)) {
			$var = json_encode($var);
			return strpos($var, '.') === false ? $var . '.0' : $var;

		} else {
			return json_encode($var);
		}
	}
}
