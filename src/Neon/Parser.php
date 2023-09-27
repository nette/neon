<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Neon;

use function array_key_exists, count, end, is_scalar, min, strlen, strncmp, substr, substr_count;


/** @internal */
final class Parser
{
	private TokenStream $stream;

	/** @var int[] */
	private $posToLine = [];


	public function parse(TokenStream $stream): Node
	{
		$this->stream = $stream;
		$this->initLines();

		while ($this->stream->tryConsume(Token::Newline));
		$node = $this->parseBlock($this->stream->getIndentation());

		while ($this->stream->tryConsume(Token::Newline));
		if (!$this->stream->is(Token::End)) {
			$this->stream->error();
		}

		return $node;
	}


	private function parseBlock(string $indent, bool $onlyBullets = false): Node
	{
		$res = new Node\BlockArrayNode($indent);
		$this->injectPos($res);
		$keyCheck = [];

		loop:
		$item = new Node\ArrayItemNode;
		$this->injectPos($item);
		if ($this->stream->tryConsume('-')) {
			// continue
		} elseif ($this->stream->is(Token::End) || $onlyBullets) {
			return $res->items
				? $res
				: $this->injectPos(new Node\LiteralNode(null));

		} else {
			$value = $this->parseValue();
			if ($this->stream->tryConsume(':', '=')) {
				$this->checkArrayKey($value, $keyCheck);
				$item->key = $value;
			} else {
				if ($res->items) {
					$this->stream->error();
				}

				return $value;
			}
		}

		$res->items[] = $item;
		$item->value = new Node\LiteralNode(null);
		$this->injectPos($item->value);

		if ($this->stream->tryConsume(Token::Newline)) {
			while ($this->stream->tryConsume(Token::Newline));
			$nextIndent = $this->stream->getIndentation();

			if (strncmp($nextIndent, $indent, min(strlen($nextIndent), strlen($indent)))) {
				$this->stream->error('Invalid combination of tabs and spaces');

			} elseif (strlen($nextIndent) > strlen($indent)) { // open new block
				$item->value = $this->parseBlock($nextIndent);

			} elseif (strlen($nextIndent) < strlen($indent)) { // close block
				return $res;

			} elseif ($item->key !== null && $this->stream->is('-')) { // special dash subblock
				$item->value = $this->parseBlock($indent, onlyBullets: true);
			}
		} elseif ($item->key === null) {  // open new block after dash
			$save = $this->stream->getIndex();
			try {
				$item->value = $this->parseBlock($indent . "\t");
			} catch (Exception) {
				$this->stream->seek($save);
				$item->value = $this->parseBlock($indent . '  ');
			}
		} elseif (!$this->stream->is(Token::End)) {
			$item->value = $this->parseValue();
			if (!$this->stream->is(Token::End, Token::Newline)) {
				$this->stream->error();
			}
		}

		if ($item->value instanceof Node\BlockArrayNode) {
			$item->value->indentation = substr($item->value->indentation, strlen($indent));
		}

		$this->injectPos($res, $res->startTokenPos, $item->value->endTokenPos);
		$this->injectPos($item, $item->startTokenPos, $item->value->endTokenPos);

		while ($this->stream->tryConsume(Token::Newline));
		if ($this->stream->is(Token::End)) {
			return $res;
		}

		$nextIndent = $this->stream->getIndentation();
		if (strncmp($nextIndent, $indent, min(strlen($nextIndent), strlen($indent)))) {
			$this->stream->error('Invalid combination of tabs and spaces');

		} elseif (strlen($nextIndent) > strlen($indent)) {
			$this->stream->error('Bad indentation');

		} elseif (strlen($nextIndent) < strlen($indent)) { // close block
			return $res;
		}

		goto loop;
	}


	private function parseValue(): Node
	{
		if ($token = $this->stream->tryConsume(Token::String)) {
			try {
				$node = new Node\StringNode(Node\StringNode::parse($token->text));
				$this->injectPos($node, $this->stream->getIndex() - 1);
			} catch (Exception $e) {
				$this->stream->error($e->getMessage(), $this->stream->getIndex() - 1);
			}
		} elseif ($token = $this->stream->tryConsume(Token::Literal)) {
			$pos = $this->stream->getIndex() - 1;
			$node = new Node\LiteralNode(Node\LiteralNode::parse($token->text, $this->stream->is(':', '=')));
			$this->injectPos($node, $pos);

		} elseif ($this->stream->is('[', '(', '{')) {
			$node = $this->parseBraces();

		} else {
			$this->stream->error();
		}

		return $this->parseEntity($node);
	}


	private function parseEntity(Node $node): Node
	{
		if (!$this->stream->is('(')) {
			return $node;
		}

		$attributes = $this->parseBraces();
		$entities[] = $this->injectPos(new Node\EntityNode($node, $attributes->items), $node->startTokenPos, $attributes->endTokenPos);

		while ($token = $this->stream->tryConsume(Token::Literal)) {
			$valueNode = new Node\LiteralNode(Node\LiteralNode::parse($token->text));
			$this->injectPos($valueNode, $this->stream->getIndex() - 1);
			if ($this->stream->is('(')) {
				$attributes = $this->parseBraces();
				$entities[] = $this->injectPos(new Node\EntityNode($valueNode, $attributes->items), $valueNode->startTokenPos, $attributes->endTokenPos);
			} else {
				$entities[] = $this->injectPos(new Node\EntityNode($valueNode), $valueNode->startTokenPos);
				break;
			}
		}

		return count($entities) === 1
			? $entities[0]
			: $this->injectPos(new Node\EntityChainNode($entities), $node->startTokenPos, end($entities)->endTokenPos);
	}


	private function parseBraces(): Node\InlineArrayNode
	{
		$token = $this->stream->tryConsume();
		$endBrace = ['[' => ']', '{' => '}', '(' => ')'][$token->text];
		$res = new Node\InlineArrayNode($token->text);
		$this->injectPos($res, $this->stream->getIndex() - 1);
		$keyCheck = [];

		loop:
		while ($this->stream->tryConsume(Token::Newline));
		if ($this->stream->tryConsume($endBrace)) {
			$this->injectPos($res, $res->startTokenPos, $this->stream->getIndex() - 1);
			return $res;
		}

		$res->items[] = $item = new Node\ArrayItemNode;
		$this->injectPos($item, $this->stream->getIndex());
		$value = $this->parseValue();

		if ($this->stream->tryConsume(':', '=')) {
			$this->checkArrayKey($value, $keyCheck);
			$item->key = $value;
			$item->value = $this->stream->is(Token::Newline, ',', $endBrace)
				? $this->injectPos(new Node\LiteralNode(null), $this->stream->getIndex())
				: $this->parseValue();
		} else {
			$item->value = $value;
		}

		$this->injectPos($item, $item->startTokenPos, $item->value->endTokenPos);

		$old = $this->stream->getIndex();
		while ($this->stream->tryConsume(Token::Newline));
		$this->stream->tryConsume(',');
		if ($old !== $this->stream->getIndex()) {
			goto loop;
		} elseif (!$this->stream->is($endBrace)) {
			$this->stream->error();
		}

		goto loop;
	}


	/** @param  true[]  $arr */
	private function checkArrayKey(Node $key, array &$arr): void
	{
		if ((!$key instanceof Node\StringNode && !$key instanceof Node\LiteralNode) || !is_scalar($key->value)) {
			$this->stream->error('Unacceptable key', $key->startTokenPos);
		}

		$k = (string) $key->value;
		if (array_key_exists($k, $arr)) {
			$this->stream->error("Duplicated key '$k'", $key->startTokenPos);
		}

		$arr[$k] = true;
	}


	private function injectPos(Node $node, ?int $start = null, ?int $end = null): Node
	{
		$node->startTokenPos = $start ?? $this->stream->getIndex();
		$node->startLine = $this->posToLine[$node->startTokenPos];
		$node->endTokenPos = $end ?? $node->startTokenPos;
		$node->endLine = $this->posToLine[$node->endTokenPos + 1] ?? end($this->posToLine);
		return $node;
	}


	private function initLines(): void
	{
		$this->posToLine = [];
		$line = 1;
		foreach ($this->stream->tokens as $token) {
			$this->posToLine[] = $line;
			$line += substr_count($token->text, "\n");
		}

		$this->posToLine[] = $line;
	}
}
