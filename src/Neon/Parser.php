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
	/** @var TokenStream */
	private $tokens;


	public function parse(TokenStream $tokens): Node
	{
		$this->tokens = $tokens;
		while ($this->tokens->consume(Token::Newline));
		$node = $this->parseBlock($this->tokens->getIndentation());

		while ($this->tokens->consume(Token::Newline));
		if ($this->tokens->isNext()) {
			$this->tokens->error();
		}

		return $node;
	}


	private function parseBlock(string $indent, bool $onlyBullets = false): Node
	{
		$res = new Node\BlockArrayNode($indent, $this->tokens->getPos());
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

		if ($this->tokens->consume(Token::Newline)) {
			while ($this->tokens->consume(Token::Newline));
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
			if ($this->tokens->isNext() && !$this->tokens->isNext(Token::Newline)) {
				$this->tokens->error();
			}
		}

		if ($item->value instanceof Node\BlockArrayNode) {
			$item->value->indentation = substr($item->value->indentation, strlen($indent));
		}

		$res->endPos = $item->endPos = $item->value->endPos;

		while ($this->tokens->consume(Token::Newline));
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
		if ($token = $this->tokens->consume(Token::String)) {
			try {
				$node = new Node\StringNode(Node\StringNode::parse($token->value), $this->tokens->getPos() - 1);
			} catch (Exception $e) {
				$this->tokens->error($e->getMessage(), $this->tokens->getPos() - 1);
			}
		} elseif ($token = $this->tokens->consume(Token::Literal)) {
			$pos = $this->tokens->getPos() - 1;
			$node = new Node\LiteralNode(Node\LiteralNode::parse($token->value, $this->tokens->isNext(':', '=')), $pos);

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

		while ($token = $this->tokens->consume(Token::Literal)) {
			$valueNode = new Node\LiteralNode(Node\LiteralNode::parse($token->value), $this->tokens->getPos() - 1);
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


	private function parseBraces(): Node\InlineArrayNode
	{
		$token = $this->tokens->consume();
		$endBrace = ['[' => ']', '{' => '}', '(' => ')'][$token->value];
		$res = new Node\InlineArrayNode($token->value, $this->tokens->getPos() - 1);
		$keyCheck = [];

		loop:
		while ($this->tokens->consume(Token::Newline));
		if ($this->tokens->consume($endBrace)) {
			$res->endPos = $this->tokens->getPos() - 1;
			return $res;
		}

		$res->items[] = $item = new Node\ArrayItemNode($this->tokens->getPos());
		$value = $this->parseValue();

		if ($this->tokens->consume(':', '=')) {
			$this->checkArrayKey($value, $keyCheck);
			$item->key = $value;
			$item->value = $this->tokens->isNext(Token::Newline, ',', $endBrace)
				? new Node\LiteralNode(null, $this->tokens->getPos())
				: $this->parseValue();
		} else {
			$item->value = $value;
		}

		$item->endPos = $item->value->endPos;

		if ($this->tokens->consume(',', Token::Newline)) {
			goto loop;
		}

		while ($this->tokens->consume(Token::Newline));
		if (!$this->tokens->isNext($endBrace)) {
			$this->tokens->error();
		}

		goto loop;
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
}
