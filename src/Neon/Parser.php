<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Neon;

use function array_key_exists, count, is_scalar, strlen;


/** @internal */
final class Parser
{
	private const Pairs = [
		Token::OpenBracket => Token::CloseBracket,
		Token::OpenBrace => Token::CloseBrace,
		Token::OpenParen => Token::CloseParen,
	];

	private TokenStream $stream;


	/** Parses a token stream into an AST. */
	public function parse(TokenStream $stream): Node
	{
		$this->stream = $stream;

		while ($this->stream->tryConsumeNewline());
		$node = $this->parseBlock($this->stream->getIndentation());

		while ($this->stream->tryConsumeNewline());
		if ($this->stream->is()) {
			$this->stream->throwUnexpected();
		}

		return $node;
	}


	private function parseBlock(string $indent, bool $onlyBullets = false): Node
	{
		$res = new Node\BlockArrayNode($indent);
		$this->injectPos($res);
		$keyCheck = [];

		while (true) {
			$item = new Node\ArrayItemNode;
			$this->injectPos($item);
			if ($this->stream->tryConsume(Token::Dash)) {
				// continue
			} elseif (!$this->stream->is() || $onlyBullets) {
				return $res->items
					? $res
					: $this->injectPos(new Node\LiteralNode(null));

			} else {
				$value = $this->parseValue();
				if ($this->stream->tryConsume(Token::Colon, Token::Equals)) {
					$this->checkArrayKey($value, $keyCheck);
					$item->key = $value;
				} else {
					if ($res->items) {
						$this->stream->throwUnexpected();
					}

					return $value;
				}
			}

			$res->items[] = $item;
			$item->value = new Node\LiteralNode(null);
			$this->injectPos($item->value);

			if ($this->stream->tryConsumeNewline()) {
				while ($this->stream->tryConsumeNewline());
				$nextIndent = $this->stream->getIndentation();

				if (strncmp($nextIndent, $indent, min(strlen($nextIndent), strlen($indent)))) {
					$this->stream->throwUnexpected('Invalid combination of tabs and spaces');

				} elseif (strlen($nextIndent) > strlen($indent)) { // open new block
					$item->value = $this->parseBlock($nextIndent);

				} elseif (strlen($nextIndent) < strlen($indent)) { // close block
					return $res;

				} elseif ($item->key !== null && $this->stream->is(Token::Dash)) { // special dash subblock
					$item->value = $this->parseBlock($indent, onlyBullets: true);
				}
			} elseif ($item->key === null) {  // open new block after dash
				$save = $this->stream->mark();
				try {
					$item->value = $this->parseBlock($indent . "\t");
				} catch (Exception) {
					$this->stream->reset($save);
					$item->value = $this->parseBlock($indent . '  ');
				}
			} elseif ($this->stream->is()) {
				$item->value = $this->parseValue();
				if ($this->stream->is() && !$this->stream->is(TokenStream::Newline)) {
					$this->stream->throwUnexpected();
				}
			}

			if ($item->value instanceof Node\BlockArrayNode) {
				$item->value->indentation = substr($item->value->indentation, strlen($indent));
			}

			$this->injectPos($res, $res->startTokenPos, $item->value->endTokenPos);
			$this->injectPos($item, $item->startTokenPos, $item->value->endTokenPos);

			while ($this->stream->tryConsumeNewline());
			if (!$this->stream->is()) {
				return $res;
			}

			$nextIndent = $this->stream->getIndentation();
			if (strncmp($nextIndent, $indent, min(strlen($nextIndent), strlen($indent)))) {
				$this->stream->throwUnexpected('Invalid combination of tabs and spaces');

			} elseif (strlen($nextIndent) > strlen($indent)) {
				$this->stream->throwUnexpected('Bad indentation');

			} elseif (strlen($nextIndent) < strlen($indent)) { // close block
				return $res;
			}
		}
	}


	private function parseValue(): Node
	{
		if ($token = $this->stream->tryConsume(Token::String)) {
			try {
				$node = new Node\StringNode(Node\StringNode::parse($token->text));
				$this->injectPos($node, $this->getIndex() - 1);
			} catch (Exception $e) {
				throw new Exception($e->getMessage(), $token->origin);
			}
		} elseif ($token = $this->stream->tryConsume(Token::Literal)) {
			$pos = $this->getIndex() - 1;
			$node = new Node\LiteralNode(Node\LiteralNode::parse($token->text, $this->stream->is(Token::Colon, Token::Equals)));
			$this->injectPos($node, $pos);

		} elseif ($this->stream->is(Token::OpenBracket, Token::OpenParen, Token::OpenBrace)) {
			$node = $this->parseBraces();

		} else {
			$this->stream->throwUnexpected();
		}

		return $this->parseEntity($node);
	}


	private function parseEntity(Node $node): Node
	{
		if (!$this->stream->is(Token::OpenParen)) {
			return $node;
		}

		$attributes = $this->parseBraces();
		$entities[] = $this->injectPos(new Node\EntityNode($node, $attributes->items), $node->startTokenPos, $attributes->endTokenPos);

		while ($token = $this->stream->tryConsume(Token::Literal)) {
			$valueNode = new Node\LiteralNode(Node\LiteralNode::parse($token->text));
			$this->injectPos($valueNode, $this->getIndex() - 1);
			if ($this->stream->is(Token::OpenParen)) {
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
		$token = $this->stream->consume();
		$endBrace = self::Pairs[$token->kind];
		$res = new Node\InlineArrayNode($token->text);
		$this->injectPos($res, $this->getIndex() - 1);
		$keyCheck = [];

		while (true) {
			while ($this->stream->tryConsumeNewline());
			if ($this->stream->tryConsume($endBrace)) {
				$this->injectPos($res, $res->startTokenPos, $this->getIndex() - 1);
				return $res;
			}

			$res->items[] = $item = new Node\ArrayItemNode;
			$this->injectPos($item, $this->getIndex());
			$value = $this->parseValue();

			if ($this->stream->tryConsume(Token::Colon, Token::Equals)) {
				$this->checkArrayKey($value, $keyCheck);
				$item->key = $value;
				$item->value = $this->stream->is(TokenStream::Newline, Token::Comma, $endBrace)
					? $this->injectPos(new Node\LiteralNode(null), $this->getIndex())
					: $this->parseValue();
			} else {
				$item->value = $value;
			}

			$this->injectPos($item, $item->startTokenPos, $item->value->endTokenPos);

			$before = $this->stream->mark();
			while ($this->stream->tryConsumeNewline());
			$this->stream->tryConsume(Token::Comma);
			if ($before !== $this->stream->mark()) {
				continue;
			} elseif (!$this->stream->is($endBrace)) {
				$this->stream->throwUnexpected();
			}
		}
	}


	/** @param  array<string, true>  $arr */
	private function checkArrayKey(Node $key, array &$arr): void
	{
		$token = $this->stream->tokens[$key->startTokenPos ?? throw new \LogicException('The key has no position.')];
		if ((!$key instanceof Node\StringNode && !$key instanceof Node\LiteralNode) || !is_scalar($key->value)) {
			throw new Exception('Unacceptable key', $token->origin);
		}

		$k = (string) $key->value;
		if (array_key_exists($k, $arr)) {
			throw new Exception("Duplicated key '$k'", $token->origin);
		}

		$arr[$k] = true;
	}


	/**
	 * @template T of Node
	 * @param T $node
	 * @return T
	 */
	private function injectPos(Node $node, ?int $start = null, ?int $end = null): Node
	{
		$tokens = $this->stream->tokens;
		$node->startTokenPos = $start ?? $this->getIndex();
		$node->startLine = $tokens[$node->startTokenPos]->origin?->line;
		$node->endTokenPos = $end ?? $node->startTokenPos;
		$last = $tokens[$node->endTokenPos];
		$node->endLine = $last->origin?->advance($last->text)->line;
		return $node;
	}


	/** @return int<0, max> */
	private function getIndex(): int
	{
		return $this->stream->mark()[0];
	}
}
