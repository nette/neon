<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Neon;

use function array_key_exists, count, in_array, is_scalar, strlen;


/** @internal */
final class Parser
{
	/** prevents stack overflow on deeply nested input */
	private const MaxDepth = 500;

	private TokenStream $tokens;
	private int $depth = 0;
	private bool $tooDeep = false;

	/** @var list<int> */
	private array $posToLine = [];


	/** Parses a token stream into an AST. */
	public function parse(TokenStream $tokens): Node
	{
		$this->tokens = $tokens;
		$this->depth = 0;
		$this->tooDeep = false;
		$this->initLines();

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
		$depth = $this->depth;
		try {
			return $this->parseBlockItems($indent, $onlyBullets);
		} finally {
			$this->depth = $depth;
		}
	}


	private function parseBlockItems(string $indent, bool $onlyBullets): Node
	{
		$res = new Node\BlockArrayNode($indent);
		$this->injectPos($res);
		$keyCheck = [];

		while (true) {
			$item = new Node\ArrayItemNode;
			$this->injectPos($item);
			if ($this->tokens->consume('-')) {
				// continue
			} elseif (!$this->tokens->isNext() || $onlyBullets) {
				return $res->items
					? $res
					: $this->injectPos(new Node\LiteralNode(null));

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

			if (!$res->items) {
				$this->enter($item->key->startTokenPos ?? $this->tokens->getPos() - 1);
			}

			$res->items[] = $item;
			$item->value = new Node\LiteralNode(null);
			$this->injectPos($item->value);

			if ($this->tokens->consume(Token::Newline)) {
				while ($this->tokens->consume(Token::Newline));
				$nextIndent = $this->tokens->getIndentation();

				if (!$this->tokens->isNext()) {
					// trailing whitespace before the end of input is not indentation

				} elseif (strncmp($nextIndent, $indent, min(strlen($nextIndent), strlen($indent)))) {
					$this->tokens->error('Invalid combination of tabs and spaces');

				} elseif (strlen($nextIndent) > strlen($indent)) { // open new block
					$item->value = $this->parseBlock($nextIndent);

				} elseif (strlen($nextIndent) < strlen($indent)) { // close block
					$this->checkImplicitKey($item, $keyCheck);
					return $res;

				} elseif ($item->key !== null && $this->tokens->isNext('-')) { // special dash subblock
					$item->value = $this->parseBlock($indent, onlyBullets: true);
				}
			} elseif ($item->key === null) {  // open new block after dash
				$item->value = $this->parseDashBlock($indent);

			} elseif ($this->tokens->isNext()) {
				$item->value = $this->parseValue();
				if ($this->tokens->isNext() && !$this->tokens->isNext(Token::Newline)) {
					$this->tokens->error();
				}
			}

			$this->checkImplicitKey($item, $keyCheck);
			if ($item->value instanceof Node\BlockArrayNode) {
				$item->value->indentation = substr($item->value->indentation, strlen($indent));
			}

			$this->injectPos($res, $res->startTokenPos, $item->value->endTokenPos);
			$this->injectPos($item, $item->startTokenPos, $item->value->endTokenPos);

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
		}
	}


	/** Opens the block after a dash: two spaces if the next line extends the indentation by a space, a tab otherwise. */
	private function parseDashBlock(string $indent): Node
	{
		$line = $this->findNextLine();
		if ($line !== null && str_starts_with($this->tokens->getIndentation($line), $indent . ' ')) {
			return $this->parseBlock($indent . '  ');
		}

		try {
			return $this->parseBlock($indent . "\t");
		} catch (Exception $e) {
			if ($line === null || $this->tokens->getPos() < $line || $this->tooDeep) {
				throw $e;
			}

			$this->tokens->error('Invalid combination of tabs and spaces', $line);
		}
	}


	/** Returns the position of the first token of the next line outside inline structures, or null. */
	private function findNextLine(): ?int
	{
		$tokens = $this->tokens->getTokens();
		$i = $this->tokens->getPos();
		$depth = 0;
		while (($tokens[$i]->type ?? null) !== Token::Newline || $depth > 0) {
			if (!isset($tokens[$i])) {
				return null;
			} elseif (in_array($tokens[$i]->type, ['[', '{', '('], true)) {
				$depth++;
			} elseif (in_array($tokens[$i]->type, [']', '}', ')'], true)) {
				$depth--;
			}

			$i++;
		}

		while (in_array($tokens[$i]->type ?? null, [Token::Newline, Token::Whitespace, Token::Comment], true)) {
			$i++;
		}

		return isset($tokens[$i]) ? $i : null;
	}


	private function parseValue(): Node
	{
		if ($token = $this->tokens->consume(Token::String)) {
			try {
				$node = new Node\StringNode(Node\StringNode::parse($token->value));
				$this->injectPos($node, $this->tokens->getPos() - 1);
			} catch (Exception $e) {
				$this->tokens->error($e->getMessage(), $this->tokens->getPos() - 1);
			}
		} elseif ($token = $this->tokens->consume(Token::Literal)) {
			$pos = $this->tokens->getPos() - 1;
			$node = new Node\LiteralNode(Node\LiteralNode::parse($token->value, $this->tokens->isNext(':', '=')));
			$this->injectPos($node, $pos);

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
		$entities[] = $this->injectPos(new Node\EntityNode($node, $attributes->items), $node->startTokenPos, $attributes->endTokenPos);

		while ($token = $this->tokens->consume(Token::Literal)) {
			$valueNode = new Node\LiteralNode(Node\LiteralNode::parse($token->value));
			$this->injectPos($valueNode, $this->tokens->getPos() - 1);
			if ($this->tokens->isNext('(')) {
				$attributes = $this->parseBraces();
				$entities[] = $this->injectPos(
					new Node\EntityNode($valueNode, $attributes->items),
					$valueNode->startTokenPos,
					$attributes->endTokenPos,
				);
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
		$depth = $this->depth;
		$this->enter($this->tokens->getPos());
		try {
			return $this->parseBracesItems();
		} finally {
			$this->depth = $depth;
		}
	}


	/** One level deeper into arrays. */
	private function enter(int $pos): void
	{
		if (++$this->depth > self::MaxDepth) {
			$this->tooDeep = true;
			$this->tokens->error('Too deep nesting', $pos);
		}
	}


	private function parseBracesItems(): Node\InlineArrayNode
	{
		$token = $this->tokens->consume();
		$endBrace = ['[' => ']', '{' => '}', '(' => ')'][$token->value];
		$res = new Node\InlineArrayNode($token->value);
		$this->injectPos($res, $this->tokens->getPos() - 1);
		$keyCheck = [];

		while (true) {
			while ($this->tokens->consume(Token::Newline));
			if ($this->tokens->consume($endBrace)) {
				$this->injectPos($res, $res->startTokenPos, $this->tokens->getPos() - 1);
				return $res;
			}

			$res->items[] = $item = new Node\ArrayItemNode;
			$this->injectPos($item, $this->tokens->getPos());
			$value = $this->parseValue();

			if ($this->tokens->consume(':', '=')) {
				$this->checkArrayKey($value, $keyCheck);
				$item->key = $value;
				$item->value = $this->tokens->isNext(Token::Newline, ',', $endBrace)
					? $this->injectPos(new Node\LiteralNode(null), $this->tokens->getPos())
					: $this->parseValue();
			} else {
				$item->value = $value;
				$this->checkImplicitKey($item, $keyCheck);
			}

			$this->injectPos($item, $item->startTokenPos, $item->value->endTokenPos);

			$old = $this->tokens->getPos();
			while ($this->tokens->consume(Token::Newline));
			$this->tokens->consume(',');
			if ($old !== $this->tokens->getPos()) {
				continue;
			} elseif (!$this->tokens->isNext($endBrace)) {
				$this->tokens->error();
			}
		}
	}


	/** @param  array<bool>  $arr  explicit keys are true */
	private function checkArrayKey(Node $key, array &$arr): void
	{
		if ((!$key instanceof Node\StringNode && !$key instanceof Node\LiteralNode) || !is_scalar($key->value)) {
			$this->tokens->error('Unacceptable key', $key->startTokenPos);
		}

		$k = (string) $key->value;
		if ($arr[$k] ?? false) {
			$this->tokens->error("Duplicated key '$k'", $key->startTokenPos);
		}

		$arr[$k] = true;
	}


	/**
	 * Gives an item without a key the next implicit key; an overflow is reported at its value, or at the item without one.
	 * @param  array<bool>  $arr
	 */
	private function checkImplicitKey(Node\ArrayItemNode $item, array &$arr): void
	{
		if ($item->key !== null) {
			return;
		} elseif (array_key_exists(PHP_INT_MAX, $arr)) {
			$pos = $item->value->startTokenPos;
			$type = $this->tokens->getTokens()[$pos]->type ?? null;
			$this->tokens->error(
				'Implicit key overflow',
				in_array($type, [null, Token::Whitespace, Token::Newline, Token::Comment], true) ? $item->startTokenPos : $pos,
			);
		}

		$arr[] = false;
	}


	/**
	 * @template T of Node
	 * @param T $node
	 * @return T
	 */
	private function injectPos(Node $node, ?int $start = null, ?int $end = null): Node
	{
		$node->startTokenPos = $start ?? $this->tokens->getPos();
		$node->startLine = $this->posToLine[$node->startTokenPos];
		$node->endTokenPos = $end ?? $node->startTokenPos;
		$node->endLine = $this->posToLine[$node->endTokenPos + 1] ?? end($this->posToLine);
		return $node;
	}


	private function initLines(): void
	{
		$this->posToLine = [];
		$line = 1;
		foreach ($this->tokens->getTokens() as $token) {
			$this->posToLine[] = $line;
			$line += substr_count($token->value, "\n");
		}

		$this->posToLine[] = $line;
	}
}
