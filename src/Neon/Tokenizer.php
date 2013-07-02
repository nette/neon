<?php

/**
 * This file is part of the Nette Framework (http://nette.org)
 *
 * Copyright (c) 2004 David Grudl (http://davidgrudl.com)
 *
 * For the full copyright and license information, please view
 * the file license.txt that was distributed with this source code.
 */

namespace Nette\Utils;

use Nette;


/**
 * Simple lexical analyser.
 *
 * @author     David Grudl
 */
class Tokenizer extends Nette\Object
{
	/** @var string */
	private $input;

	/** @var array */
	public $tokens;

	/** @var string */
	private $re;

	/** @var array */
	private $types;


	/**
	 * @param  array of [symbol type => pattern]
	 * @param  string  regular expression flag
	 */
	public function __construct(array $patterns, $flags = '')
	{
		$this->re = '~(' . implode(')|(', $patterns) . ')~A' . $flags;
		$keys = array_keys($patterns);
		$this->types = $keys === range(0, count($patterns) - 1) ? FALSE : $keys;
	}


	/**
	 * Tokenize string.
	 * @param  string
	 * @return array
	 */
	public function tokenize($input)
	{
		$this->input = $input;
		if ($this->types) {
			$this->tokens = Strings::matchAll($input, $this->re);
			$len = 0;
			$count = count($this->types);
			$line = 1;
			foreach ($this->tokens as & $match) {
				$type = NULL;
				for ($i = 1; $i <= $count; $i++) {
					if (!isset($match[$i])) {
						break;
					} elseif ($match[$i] != NULL) {
						$type = $this->types[$i - 1]; break;
					}
				}
				$match = self::createToken($match[0], $type, $line);
				$len += strlen($match['value']);
				$line += substr_count($match['value'], "\n");
			}
			if ($len !== strlen($input)) {
				$errorOffset = $len;
			}

		} else {
			$this->tokens = Strings::split($input, $this->re, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_OFFSET_CAPTURE);
			$last = end($this->tokens);
			if ($this->tokens && !Strings::match($last[0], $this->re)) {
				$errorOffset = $last[1];
			}
		}

		if (isset($errorOffset)) {
			list($line, $col) = $this->getCoordinates($this->input, $errorOffset);
			$token = str_replace("\n", '\n', substr($input, $errorOffset, 10));
			throw new TokenizerException("Unexpected '$token' on line $line, column $col.");
		}
		return $this->tokens;
	}



	public static function createToken($value, $type = NULL, $line = NULL)
	{
		return array('value' => $value, 'type' => $type, 'line' => $line);
	}


	/**
	 * Returns position of token in input string.
	 * @param  int token number
	 * @return array [offset, line, column]
	 */
	public function getOffset($i)
	{
		$tokens = Strings::split($this->input, $this->re, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_OFFSET_CAPTURE);
		$offset = isset($tokens[$i]) ? $tokens[$i][1] : strlen($this->input);
		list($line, $col) = $this->getCoordinates($this->input, $offset);
		return array($offset, $line, $col);
	}



	/**
	 * Returns coordinates of offset in string.
	 * @return array [line, column]
	 */
	public static function getCoordinates($text, $offset)
	{
		$text = substr($text, 0, $offset);
		return array(substr_count($text, "\n") + 1, $offset - strrpos("\n" . $text, "\n") + 1);
	}

}
