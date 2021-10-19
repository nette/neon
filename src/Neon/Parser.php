<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Neon;


/** @internal */
final class Parser
{
	private const PATTERN_DATETIME = '#\d\d\d\d-\d\d?-\d\d?(?:(?:[Tt]| ++)\d\d?:\d\d:\d\d(?:\.\d*+)? *+(?:Z|[-+]\d\d?(?::?\d\d)?)?)?$#DA';
	private const PATTERN_HEX = '#0x[0-9a-fA-F]++$#DA';
	private const PATTERN_OCTAL = '#0o[0-7]++$#DA';
	private const PATTERN_BINARY = '#0b[0-1]++$#DA';

	private const SIMPLE_TYPES = [
		'true' => true, 'True' => true, 'TRUE' => true, 'yes' => true, 'Yes' => true, 'YES' => true, 'on' => true, 'On' => true, 'ON' => true,
		'false' => false, 'False' => false, 'FALSE' => false, 'no' => false, 'No' => false, 'NO' => false, 'off' => false, 'Off' => false, 'OFF' => false,
		'null' => null, 'Null' => null, 'NULL' => null,
	];

	private const DEPRECATED_TYPES = ['on' => 1, 'On' => 1, 'ON' => 1, 'off' => 1, 'Off' => 1, 'OFF' => 1];

	private const ESCAPE_SEQUENCES = [
		't' => "\t", 'n' => "\n", 'r' => "\r", 'f' => "\x0C", 'b' => "\x08", '"' => '"', '\\' => '\\', '/' => '/', '_' => "\u{A0}",
	];

	/** @var TokenStream */
	private $tokens;


	public function parse(TokenStream $tokens)
	{
		$this->tokens = $tokens;
		while ($this->tokens->consume(Token::NEWLINE));
		$res = $this->parseBlock($this->tokens->getIndentation());

		while ($this->tokens->consume(Token::NEWLINE));
		if ($this->tokens->isNext()) {
			$this->tokens->error();
		}
		return $res;
	}


	private function parseBlock(string $indent, bool $onlyBullets = false)
	{
		$res = null;

		loop:
		if ($this->tokens->consume('-')) {
			$key = null;

		} elseif (!$this->tokens->isNext() || $onlyBullets) {
			return $res;

		} else {
			$valuePos = $this->tokens->getPos();
			$value = $this->parseValue();
			if ($this->tokens->consume(':', '=')) {
				$key = $this->checkArrayKey($value, (array) $res, $valuePos);
			} else {
				if ($res) {
					$this->tokens->error();
				}
				return $value;
			}
		}

		$value = null;

		if ($this->tokens->consume(Token::NEWLINE)) {
			while ($this->tokens->consume(Token::NEWLINE));
			$nextIndent = $this->tokens->getIndentation();

			if (strncmp($nextIndent, $indent, min(strlen($nextIndent), strlen($indent)))) {
				$this->tokens->error('Invalid combination of tabs and spaces');

			} elseif (strlen($nextIndent) > strlen($indent)) { // open new block
				$value = $this->parseBlock($nextIndent);

			} elseif (strlen($nextIndent) < strlen($indent)) { // close block

			} elseif ($key !== null && $this->tokens->isNext('-')) { // special dash subblock
				$value = $this->parseBlock($indent, true);
			}
		} elseif ($key === null) {
			$value = $this->parseBlock($indent . '  '); // open new block after dash

		} elseif ($this->tokens->isNext()) {
			$value = $this->parseValue();
			if ($this->tokens->isNext() && !$this->tokens->isNext(Token::NEWLINE)) {
				$this->tokens->error();
			}
		}

		if ($key === null) {
			$res[] = $value;
		} else {
			$res[$key] = $value;
		}

		while ($this->tokens->consume(Token::NEWLINE));
		if (!$this->tokens->isNext()) {
			return $res;
		}

		$nextIndent = $this->tokens->getIndentation();
		if (strncmp($nextIndent, $indent, min(strlen($nextIndent), strlen($indent)))) {
			$this->tokens->error('Invalid combination of tabs and spaces');

		} elseif (strlen($nextIndent) > strlen($indent)) {
			$this->tokens->error('Bad indentation');

		} elseif (strlen($nextIndent) < strlen($indent)) { // close block
			return $res;
		}

		goto loop;
	}


	private function parseValue()
	{
		if ($token = $this->tokens->consume(Token::STRING)) {
			$value = $this->decodeString($token->value);

		} elseif ($token = $this->tokens->consume(Token::LITERAL)) {
			$value = $this->literalToValue($token->value, $this->tokens->isNext(':', '='));

		} elseif ($this->tokens->isNext('[', '(', '{')) {
			$value = $this->parseBraces();

		} else {
			$this->tokens->error();
		}
		return $this->parseEntity($value);
	}


	private function parseEntity($value)
	{
		if (!$this->tokens->isNext('(')) {
			return $value;
		}

		$entities[] = new Entity($value, $this->parseBraces());
		while ($token = $this->tokens->consume(Token::LITERAL)) {
			$value = $this->literalToValue($token->value);
			if ($this->tokens->isNext('(')) {
				$entities[] = new Entity($value, $this->parseBraces());
			} else {
				$entities[] = new Entity($value, []);
				break;
			}
		}
		return count($entities) === 1
			? $entities[0]
			: new Entity(Neon::CHAIN, $entities);
	}


	private function parseBraces(): array
	{
		$endBrace = ['[' => ']', '{' => '}', '(' => ')'][$this->tokens->consume()->value];
		$res = [];

		loop:
		while ($this->tokens->consume(Token::NEWLINE));
		if ($this->tokens->consume($endBrace)) {
			return $res;
		}

		$valuePos = $this->tokens->getPos();
		$value = $this->parseValue();

		if ($this->tokens->consume(':', '=')) {
			$key = $this->checkArrayKey($value, $res, $valuePos);
			$res[$key] = $this->tokens->isNext(Token::NEWLINE, ',', $endBrace)
				? null
				: $this->parseValue();
		} else {
			$res[] = $value;
		}

		if ($this->tokens->consume(',', Token::NEWLINE)) {
			goto loop;
		}
		while ($this->tokens->consume(Token::NEWLINE));
		if (!$this->tokens->isNext($endBrace)) {
			$this->tokens->error();
		}
		goto loop;
	}


	private function decodeString(string $s): string
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
		if ($s[0] === '"') {
			$res = preg_replace_callback(
				'#\\\\(?:ud[89ab][0-9a-f]{2}\\\\ud[c-f][0-9a-f]{2}|u[0-9a-f]{4}|x[0-9a-f]{2}|.)#i',
				function (array $m): string {
					$sq = $m[0];
					if (isset(self::ESCAPE_SEQUENCES[$sq[1]])) {
						return self::ESCAPE_SEQUENCES[$sq[1]];
					} elseif ($sq[1] === 'u' && strlen($sq) >= 6) {
						return $this->decodeUnicodeSequence($sq);
					} elseif ($sq[1] === 'x' && strlen($sq) === 4) {
						trigger_error("Neon: '$sq' is deprecated, use '\\uXXXX' instead.", E_USER_DEPRECATED);
						return chr(hexdec(substr($sq, 2)));
					} else {
						$this->tokens->error("Invalid escaping sequence $sq", $this->tokens->getPos() - 1);
					}
				},
				$res
			);
		}
		return $res;
	}


	private function decodeUnicodeSequence(string $sq): string
	{
		$lead = hexdec(substr($sq, 2, 4));
		$tail = hexdec(substr($sq, 8, 4));
		$code = $tail ? (0x2400 + (($lead - 0xD800) << 10) + $tail) : $lead;
		if ($code >= 0xD800 && $code <= 0xDFFF) {
			$this->tokens->error("Invalid UTF-8 (lone surrogate) $sq", $this->tokens->getPos() - 1);
		}
		return function_exists('iconv')
			? iconv('UTF-32BE', 'UTF-8//IGNORE', pack('N', $code))
			: mb_convert_encoding(pack('N', $code), 'UTF-8', 'UTF-32BE');
	}


	private function checkArrayKey($key, array $arr, int $pos): string
	{
		if (!is_scalar($key)) {
			$this->tokens->error('Unacceptable key', $pos);
		}
		$key = (string) $key;
		if (array_key_exists($key, $arr)) {
			$this->tokens->error("Duplicated key '$key'", $pos);
		}
		return $key;
	}


	/** @return mixed */
	public function literalToValue(string $value, bool $isKey = false)
	{
		if (!$isKey && array_key_exists($value, self::SIMPLE_TYPES)) {
			if (isset(self::DEPRECATED_TYPES[$value])) {
				trigger_error("Neon: keyword '$value' is deprecated, use true/yes or false/no.", E_USER_DEPRECATED);
			}
			return self::SIMPLE_TYPES[$value];

		} elseif (is_numeric($value)) {
			return $value * 1;

		} elseif (preg_match(self::PATTERN_HEX, $value)) {
			return hexdec($value);

		} elseif (preg_match(self::PATTERN_OCTAL, $value)) {
			return octdec($value);

		} elseif (preg_match(self::PATTERN_BINARY, $value)) {
			return bindec($value);

		} elseif (!$isKey && preg_match(self::PATTERN_DATETIME, $value)) {
			return new \DateTimeImmutable($value);

		} else {
			return $value;
		}
	}
}
