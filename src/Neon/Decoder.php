<?php

/**
 * This file is part of the Nette Framework (http://nette.org)
 * Copyright (c) 2004 David Grudl (http://davidgrudl.com)
 */

declare(strict_types = 1);

namespace Nette\Neon;


/**
 * Parser for Nette Object Notation.
 * @internal
 */
class Decoder
{
	/** @var array */
	public static $patterns = [
		'
			\'[^\'\n]*\' |
			"(?: \\\\. | [^"\\\\\n] )*"
		', // string
		'
			(?: [^#"\',:=[\]{}()\x00-\x20!`-] | [:-][^"\',\]})\s] )
			(?:
				[^,:=\]})(\x00-\x20]+ |
				:(?! [\s,\]})] | $ ) |
				[\ \t]+ [^#,:=\]})(\x00-\x20]
			)*
		', // literal / boolean / integer / float
		'
			[,:=[\]{}()-]
		', // symbol
		'?:\#.*', // comment
		'\n[\t\ ]*', // new line + indent
		'?:[\t\ ]+', // whitespace
	];

	private static $brackets = [
		'[' => ']',
		'{' => '}',
		'(' => ')',
	];

	/** @var string */
	private $input;

	/** @var array */
	private $tokens = [];

	/** @var int */
	private $pos;



	/**
	 * Decodes a NEON string.
	 * @param  string
	 * @return mixed
	 */
	public function decode(string $input)
	{
		if (substr($input, 0, 3) === "\xEF\xBB\xBF") { // BOM
			$input = substr($input, 3);
		}
		$this->input = "\n" . str_replace("\r", '', $input); // \n forces indent detection

		$pattern = '~(' . implode(')|(', self::$patterns) . ')~Amix';
		$this->tokens = preg_split($pattern, $this->input, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_OFFSET_CAPTURE | PREG_SPLIT_DELIM_CAPTURE);

		$last = end($this->tokens);
		if ($this->tokens && !preg_match($pattern, $last[0])) {
			$this->pos = count($this->tokens) - 1;
			$this->error();
		}

		$this->pos = 0;
		$res = $this->parse(NULL);

		while (isset($this->tokens[$this->pos])) {
			if ($this->tokens[$this->pos][0][0] === "\n") {
				$this->pos++;
			} else {
				$this->error();
			}
		}
		return $res;
	}


	/**
	 * @param  mixed  indentation (for block-parser)
	 * @param  mixed
	 * @return array
	 */
	private function parse($indent, array $result = NULL, string $key = NULL, bool $hasKey = FALSE)
	{
		$inlineParser = $indent === FALSE;
		$value = NULL;
		$hasValue = FALSE;
		$tokens = $this->tokens;
		$n = & $this->pos;
		$count = count($tokens);
		$mainResult = & $result;

		for (; $n < $count; $n++) {
			$t = $tokens[$n][0];

			if ($t === ',') { // ArrayEntry separator
				if ((!$hasKey && !$hasValue) || !$inlineParser) {
					$this->error();
				}
				$this->addValue($result, $hasKey ? $key : NULL, $hasValue ? $value : NULL);
				$hasKey = $hasValue = FALSE;

			} elseif ($t === ':' || $t === '=') { // KeyValuePair separator
				if ($hasValue && (is_array($value) || is_object($value))) {
					$this->error('Unacceptable key');

				} elseif ($hasKey && $key === NULL && $hasValue && !$inlineParser) {
					$n++;
					$result[] = $this->parse($indent . '  ', [], $value, TRUE);
					$newIndent = isset($tokens[$n], $tokens[$n + 1]) ? (string) substr($tokens[$n][0], 1) : ''; // not last
					if (strlen($newIndent) > strlen($indent)) {
						$n++;
						$this->error('Bad indentation');
					} elseif (strlen($newIndent) < strlen($indent)) {
						return $mainResult; // block parser exit point
					}
					$hasKey = $hasValue = FALSE;

				} elseif ($hasKey || !$hasValue) {
					$this->error();

				} else {
					$key = (string) $value;
					$hasKey = TRUE;
					$hasValue = FALSE;
					$result = & $mainResult;
				}

			} elseif ($t === '-') { // BlockArray bullet
				if ($hasKey || $hasValue || $inlineParser) {
					$this->error();
				}
				$key = NULL;
				$hasKey = TRUE;

			} elseif (isset(self::$brackets[$t])) { // Opening bracket [ ( {
				if ($hasValue) {
					if ($t !== '(') {
						$this->error();
					}
					$n++;
					if ($value instanceof Entity && $value->value === Neon::CHAIN) {
						end($value->attributes)->attributes = $this->parse(FALSE, []);
					} else {
						$value = new Entity($value, $this->parse(FALSE, []));
					}
				} else {
					$n++;
					$value = $this->parse(FALSE, []);
				}
				$hasValue = TRUE;
				if (!isset($tokens[$n]) || $tokens[$n][0] !== self::$brackets[$t]) { // unexpected type of bracket or block-parser
					$this->error();
				}

			} elseif ($t === ']' || $t === '}' || $t === ')') { // Closing bracket ] ) }
				if (!$inlineParser) {
					$this->error();
				}
				break;

			} elseif ($t[0] === "\n") { // Indent
				if ($inlineParser) {
					if ($hasKey || $hasValue) {
						$this->addValue($result, $hasKey ? $key : NULL, $hasValue ? $value : NULL);
						$hasKey = $hasValue = FALSE;
					}

				} else {
					while (isset($tokens[$n + 1]) && $tokens[$n + 1][0][0] === "\n") {
						$n++; // skip to last indent
					}
					if (!isset($tokens[$n + 1])) {
						break;
					}

					$newIndent = (string) substr($tokens[$n][0], 1);
					if ($indent === NULL) { // first iteration
						$indent = $newIndent;
					}
					$minlen = min(strlen($newIndent), strlen($indent));
					if ($minlen && (string) substr($newIndent, 0, $minlen) !== (string) substr($indent, 0, $minlen)) {
						$n++;
						$this->error('Invalid combination of tabs and spaces');
					}

					if (strlen($newIndent) > strlen($indent)) { // open new block-array or hash
						if ($hasValue || !$hasKey) {
							$n++;
							$this->error('Bad indentation');
						}
						$this->addValue($result, $key, $this->parse($newIndent));
						$newIndent = isset($tokens[$n], $tokens[$n + 1]) ? (string) substr($tokens[$n][0], 1) : ''; // not last
						if (strlen($newIndent) > strlen($indent)) {
							$n++;
							$this->error('Bad indentation');
						}
						$hasKey = FALSE;

					} else {
						if ($hasValue && !$hasKey) { // block items must have "key"; NULL key means list item
							break;

						} elseif ($hasKey) {
							$this->addValue($result, $key, $hasValue ? $value : NULL);
							if ($key !== NULL && !$hasValue && $newIndent === $indent && isset($tokens[$n + 1]) && $tokens[$n + 1][0] === '-') {
								$result = & $result[$key];
							}
							$hasKey = $hasValue = FALSE;
						}
					}

					if (strlen($newIndent) < strlen($indent)) { // close block
						return $mainResult; // block parser exit point
					}
				}

			} elseif ($hasValue) { // Value
				if ($value instanceof Entity) { // Entity chaining
					if ($value->value !== Neon::CHAIN) {
						$value = new Entity(Neon::CHAIN, [$value]);
					}
					$value->attributes[] = new Entity($t);
				} else {
					$this->error();
				}
			} else { // Value
				static $consts = [
					'true' => TRUE, 'True' => TRUE, 'TRUE' => TRUE, 'yes' => TRUE, 'Yes' => TRUE, 'YES' => TRUE, 'on' => TRUE, 'On' => TRUE, 'ON' => TRUE,
					'false' => FALSE, 'False' => FALSE, 'FALSE' => FALSE, 'no' => FALSE, 'No' => FALSE, 'NO' => FALSE, 'off' => FALSE, 'Off' => FALSE, 'OFF' => FALSE,
					'null' => 0, 'Null' => 0, 'NULL' => 0,
				];
				if ($t[0] === '"') {
					$value = preg_replace_callback('#\\\\(?:ud[89ab][0-9a-f]{2}\\\\ud[c-f][0-9a-f]{2}|u[0-9a-f]{4}|x[0-9a-f]{2}|.)#i', [$this, 'cbString'], substr($t, 1, -1));
				} elseif ($t[0] === "'") {
					$value = substr($t, 1, -1);
				} elseif (isset($consts[$t]) && (!isset($tokens[$n + 1][0]) || ($tokens[$n + 1][0] !== ':' && $tokens[$n + 1][0] !== '='))) {
					$value = $consts[$t] === 0 ? NULL : $consts[$t];
				} elseif (is_numeric($t)) {
					$value = $t * 1;
				} elseif (preg_match('#0x[0-9a-fA-F]+\z#A', $t)) {
					$value = hexdec($t);
				} elseif (preg_match('#\d\d\d\d-\d\d?-\d\d?(?:(?:[Tt]| +)\d\d?:\d\d:\d\d(?:\.\d*)? *(?:Z|[-+]\d\d?(?::\d\d)?)?)?\z#A', $t)) {
					$value = new \DateTime($t);
				} else { // literal
					$value = $t;
				}
				$hasValue = TRUE;
			}
		}

		if ($inlineParser) {
			if ($hasKey || $hasValue) {
				$this->addValue($result, $hasKey ? $key : NULL, $hasValue ? $value : NULL);
			}
		} else {
			if ($hasValue && !$hasKey) { // block items must have "key"
				if ($result === NULL) {
					$result = $value; // simple value parser
				} else {
					$this->error();
				}
			} elseif ($hasKey) {
				$this->addValue($result, $key, $hasValue ? $value : NULL);
			}
		}
		return $mainResult;
	}


	private function addValue(& $result, $key, $value)
	{
		if ($key === NULL) {
			$result[] = $value;
		} elseif ($result && array_key_exists($key, $result)) {
			$this->error("Duplicated key '$key'");
		} else {
			$result[$key] = $value;
		}
	}


	private function cbString($m)
	{
		static $mapping = ['t' => "\t", 'n' => "\n", 'r' => "\r", 'f' => "\x0C", 'b' => "\x08", '"' => '"', '\\' => '\\', '/' => '/', '_' => "\xc2\xa0"];
		$sq = $m[0];
		if (isset($mapping[$sq[1]])) {
			return $mapping[$sq[1]];
		} elseif ($sq[1] === 'u' && strlen($sq) >= 6) {
			$lead = hexdec(substr($sq, 2, 4));
			$tail = hexdec(substr($sq, 8, 4));
			$code = $tail ? (0x2400 + (($lead - 0xD800) << 10) + $tail) : $lead;
			if ($code >= 0xD800 && $code <= 0xDFFF) {
				$this->error("Invalid UTF-8 (lone surrogate) $sq");
			}
			return iconv('UTF-32BE', 'UTF-8//IGNORE', pack('N', $code));
		} elseif ($sq[1] === 'x' && strlen($sq) === 4) {
			return chr(hexdec(substr($sq, 2)));
		} else {
			$this->error("Invalid escaping sequence $sq");
		}
	}


	private function error(string $message = "Unexpected '%s'")
	{
		$last = isset($this->tokens[$this->pos]) ? $this->tokens[$this->pos] : NULL;
		$offset = $last ? $last[1] : strlen($this->input);
		$text = substr($this->input, 0, $offset);
		$line = substr_count($text, "\n");
		$col = $offset - strrpos("\n" . $text, "\n") + 1;
		$token = $last ? str_replace("\n", '<new line>', substr($last[0], 0, 40)) : 'end';
		throw new Exception(str_replace('%s', $token, $message) . " on line $line, column $col.");
	}

}
