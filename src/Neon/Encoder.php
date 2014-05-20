<?php

/**
 * This file is part of the Nette Framework (http://nette.org)
 * Copyright (c) 2004 David Grudl (http://davidgrudl.com)
 */

namespace Nette\Neon;

use Nette;


/**
 * Simple generator for Nette Object Notation.
 *
 * @author     David Grudl
 */
class Encoder
{
	const BLOCK = 1;


	/**
	 * Returns the NEON representation of a value.
	 * @param  mixed
	 * @param  int
	 * @return string
	 */
	public function encode($var, $options = NULL)
	{
		if ($var instanceof \DateTime) {
			return $var->format('Y-m-d H:i:s O');

		} elseif ($var instanceof Entity) {
			return self::encode($var->value) . '(' . (is_array($var->attributes) ? substr(self::encode($var->attributes), 1, -1) : '') . ')';
		}

		if (is_object($var)) {
			$obj = $var; $var = array();
			foreach ($obj as $k => $v) {
				$var[$k] = $v;
			}
		}

		if (is_array($var)) {
			$isList = !$var || array_keys($var) === range(0, count($var) - 1);
			$s = '';
			if ($options & self::BLOCK) {
				if (count($var) === 0) {
					return '[]';
				}
				foreach ($var as $k => $v) {
					$v = self::encode($v, self::BLOCK);
					$s .= ($isList ? '-' : self::encode($k) . ':')
						. (strpos($v, "\n") === FALSE ? ' ' . $v : "\n\t" . str_replace("\n", "\n\t", $v))
						. "\n";
					continue;
				}
				return $s;

			} else {
				foreach ($var as $k => $v) {
					$s .= ($isList ? '' : self::encode($k) . ': ') . self::encode($v) . ', ';
				}
				return ($isList ? '[' : '{') . substr($s, 0, -2) . ($isList ? ']' : '}');
			}

		} elseif (is_string($var) && !is_numeric($var)
			&& !preg_match('~[\x00-\x1F]|^\d{4}|^(true|false|yes|no|on|off|null)\z~i', $var)
			&& preg_match('~^' . Decoder::$patterns[1] . '\z~x', $var) // 1 = literals
		) {
			return $var;

		} elseif (is_float($var)) {
			$var = json_encode($var);
			return strpos($var, '.') === FALSE ? $var . '.0' : $var;

		} else {
			return json_encode($var);
		}
	}

}
