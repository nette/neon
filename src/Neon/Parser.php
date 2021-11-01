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


	public function parse(TokenStream $tokens): Node
	{
		$this->tokens = $tokens;
		while ($this->tokens->consume(Token::NEWLINE));
		$node = $this->parseBlock($this->tokens->getIndentation());

		while ($this->tokens->consume(Token::NEWLINE));
		if ($this->tokens->isNext()) {
			$this->tokens->error();
		}
		return $node;
	}


	private function parseBlock(string $indent, bool $onlyBullets = false): Node
	{
		$res = new Node\ArrayNode($indent, $this->tokens->getPos());
		$keyCheck = [];

		loop:
		$item = new Node\ArrayItemNode($this->tokens->getPos());
		if ($this->tokens->consume('-')) {
			// continue
		} elseif (!$this->tokens->isNext() || $onlyBullets) {
			return $res->items
				? $res
				: new Node\LiteralNode(null, $this->tokens->getPos());

		} else {
			$value = $this->parseValue();
			if ($this->tokens->consume(':', '=')) {
				$this->checkArrayKey($value, $keyCheck);
				$item->key = $value;
			} else {
				if ($res->items) {
					$this->tokens->error();
				}
				return $value;
			}
		}

		$res->items[] = $item;
		$item->value = new Node\LiteralNode(null, $this->tokens->getPos());

		if ($this->tokens->consume(Token::NEWLINE)) {
			while ($this->tokens->consume(Token::NEWLINE));
			$nextIndent = $this->tokens->getIndentation();

			if (strncmp($nextIndent, $indent, min(strlen($nextIndent), strlen($indent)))) {
				$this->tokens->error('Invalid combination of tabs and spaces');

			} elseif (strlen($nextIndent) > strlen($indent)) { // open new block
				$item->value = $this->parseBlock($nextIndent);

			} elseif (strlen($nextIndent) < strlen($indent)) { // close block
				return $res;

			} elseif ($item->key !== null && $this->tokens->isNext('-')) { // special dash subblock
				$item->value = $this->parseBlock($indent, true);
			}
		} elseif ($item->key === null) {
			$item->value = $this->parseBlock($indent . '  '); // open new block after dash

		} elseif ($this->tokens->isNext()) {
			$item->value = $this->parseValue();
			if ($this->tokens->isNext() && !$this->tokens->isNext(Token::NEWLINE)) {
				$this->tokens->error();
			}
		}

		if ($item->value instanceof Node\ArrayNode && is_string($item->value->indentation)) {
			$item->value->indentation = substr($item->value->indentation, strlen($indent));
		}

		$res->endPos = $item->endPos = $item->value->endPos;

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


	private function parseValue(): Node
	{
		if ($token = $this->tokens->consume(Token::STRING)) {
			$node = new Node\StringNode($this->decodeString($token->value), $this->tokens->getPos() - 1);

		} elseif ($token = $this->tokens->consume(Token::LITERAL)) {
			$pos = $this->tokens->getPos() - 1;
			$node = new Node\LiteralNode($this->literalToValue($token->value, $this->tokens->isNext(':', '=')), $pos);

		} elseif ($this->tokens->isNext('[', '(', '{')) {
			$node = $this->parseBraces();

		} else {
			$this->tokens->error();
		}
		return $this->parseEntity($node);
	}


	private function parseEntity(Node $node): Node
	{
		if (!$this->tokens->isNext('(')) {
			return $node;
		}

		$attributes = $this->parseBraces();
		$entities[] = new Node\EntityNode($node, $attributes->items, $node->startPos, $attributes->endPos);

		while ($token = $this->tokens->consume(Token::LITERAL)) {
			$valueNode = new Node\LiteralNode($this->literalToValue($token->value), $this->tokens->getPos() - 1);
			if ($this->tokens->isNext('(')) {
				$attributes = $this->parseBraces();
				$entities[] = new Node\EntityNode($valueNode, $attributes->items, $valueNode->startPos, $attributes->endPos);
			} else {
				$entities[] = new Node\EntityNode($valueNode, [], $valueNode->startPos);
				break;
			}
		}
		return count($entities) === 1
			? $entities[0]
			: new Node\EntityChainNode($entities, $node->startPos, end($entities)->endPos);
	}


	private function parseBraces(): Node\ArrayNode
	{
		$token = $this->tokens->consume();
		$endBrace = ['[' => ']', '{' => '}', '(' => ')'][$token->value];
		$res = new Node\ArrayNode(null, $this->tokens->getPos() - 1);
		$keyCheck = [];

		loop:
		while ($this->tokens->consume(Token::NEWLINE));
		if ($this->tokens->consume($endBrace)) {
			$res->endPos = $this->tokens->getPos() - 1;
			return $res;
		}

		$res->items[] = $item = new Node\ArrayItemNode($this->tokens->getPos());
		$value = $this->parseValue();

		if ($this->tokens->consume(':', '=')) {
			$this->checkArrayKey($value, $keyCheck);
			$item->key = $value;
			$item->value = $this->tokens->isNext(Token::NEWLINE, ',', $endBrace)
				? new Node\LiteralNode(null, $this->tokens->getPos())
				: $this->parseValue();
		} else {
			$item->value = $value;
		}
		$item->endPos = $item->value->endPos;

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
						return json_decode('"' . $sq . '"') ?? $this->tokens->error("Invalid UTF-8 sequence $sq", $this->tokens->getPos() - 1);
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


	private function checkArrayKey(Node $key, array &$arr): void
	{
		if ((!$key instanceof Node\StringNode && !$key instanceof Node\LiteralNode) || !is_scalar($key->value)) {
			$this->tokens->error('Unacceptable key', $key->startPos);
		}
		$k = (string) $key->value;
		if (array_key_exists($k, $arr)) {
			$this->tokens->error("Duplicated key '$k'", $key->startPos);
		}
		$arr[$k] = true;
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
