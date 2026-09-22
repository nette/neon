<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Neon;

use function array_key_exists, count, is_scalar, is_string, strlen;


/** @internal */
final class Parser
{
	public const MaxDepth = 500;
	private const TooDeep = 'Too deep nesting';
	private const InvalidIndentation = 'Invalid combination of tabs and spaces';

	private const Pairs = [
		Token::OpenBracket => Token::CloseBracket,
		Token::OpenBrace => Token::CloseBrace,
		Token::OpenParen => Token::CloseParen,
	];

	private TokenStream $stream;
	private int $depth = 0;


	/** Parses a token stream into an AST. */
	public function parse(TokenStream $stream): Node
	{
		$this->stream = $stream;
		$this->depth = 0;

		while ($this->stream->tryConsumeNewline());
		$node = $this->parseBlock($this->stream->getIndentation());

		while ($this->stream->tryConsumeNewline());
		if ($this->stream->is()) {
			$this->stream->throwUnexpected();
		}

		return $node;
	}


	/** @param  string|\Closure(string): string  $indent */
	private function parseBlock(string|\Closure $indent, bool $onlyBullets = false): Node
	{
		$depth = $this->depth;
		try {
			return $this->parseBlockItems($indent, $onlyBullets);
		} finally {
			$this->depth = $depth;
		}
	}


	/**
	 * A block opened on the line of a dash is indented by a tab if the line where its indentation is first
	 * compared starts with one, by two spaces otherwise. 3.x tries the tab and then the spaces, which costs time
	 * exponential in nested dashes; the spaces fail on that line whenever the tab was chosen, so a block
	 * that fails with the tab fails as the spaces would.
	 * @param  string|\Closure(string): string  $base
	 */
	private function parseDashBlock(string|\Closure $base): Node
	{
		$resolved = $line = null;
		$indent = function (string $next) use ($base, &$resolved, &$line): string {
			if ($resolved === null) {
				$base = $this->resolve($base, $next);
				$resolved = $base . (str_starts_with($next, $base . "\t") ? "\t" : '  ');
				$line = $this->stream->mark();
			}
			return $resolved;
		};

		try {
			return $this->parseBlock($indent);
		} catch (Exception $e) {
			if ($e->getDescription() !== self::TooDeep && $resolved !== null && str_ends_with($resolved, "\t") && $line) {
				$this->stream->reset($line);
				$this->stream->throwUnexpected(self::InvalidIndentation);
			}
			throw $e;
		}
	}


	/**
	 * The indentation of a block, decided for a block opened on the line of a dash by the indentation of the next line.
	 * @param  string|\Closure(string): string  $indent
	 */
	private function resolve(string|\Closure $indent, string $next): string
	{
		return is_string($indent) ? $indent : $indent($next);
	}


	/** @param  string|\Closure(string): string  $pending */
	private function parseBlockItems(string|\Closure $pending, bool $onlyBullets): Node
	{
		$stream = $this->stream;
		$res = new Node\BlockArrayNode;
		$this->injectPos($res);
		$keyCheck = [];

		while (true) {
			$item = new Node\ArrayItemNode;
			$this->injectPos($item);
			if ($bullet = $stream->tryConsume(Token::Dash)) {
				if (!$res->items) {
					$this->enter($bullet);
				}
			} elseif (!$stream->is() || $onlyBullets) {
				return $res->items
					? $res
					: $this->injectPos(new Node\LiteralNode(null));

			} else {
				$value = $this->parseValue();
				if ($stream->tryConsume(Token::Colon, Token::Equals)) {
					$this->checkArrayKey($value, $keyCheck);
					$item->key = $value;
					if (!$res->items) {
						$this->enter($stream->tokens[$value->startTokenPos ?? throw new \LogicException('The key has no position.')]);
					}
				} else {
					if ($res->items) {
						$stream->throwUnexpected();
					}

					return $value;
				}
			}

			$res->items[] = $item;
			$item->value = new Node\LiteralNode(null);
			$this->injectPos($item->value);

			if ($stream->tryConsumeNewline()) {
				while ($stream->tryConsumeNewline());
				$nextIndent = $stream->getIndentation();
				$indent = $this->resolve($pending, $nextIndent);
				if (!self::isComparable($nextIndent, $indent)) {
					$stream->throwUnexpected(self::InvalidIndentation);

				} elseif (strlen($nextIndent) > strlen($indent)) { // open new block
					$item->value = $this->parseBlock($nextIndent);

				} elseif (strlen($nextIndent) < strlen($indent)) { // close block
					return $res;

				} elseif ($item->key !== null && $stream->is(Token::Dash)) { // special dash subblock
					$item->value = $this->parseBlock($indent, onlyBullets: true);
				}
			} elseif ($item->key === null) { // open new block after dash
				$item->value = $this->parseDashBlock($pending);

			} elseif ($stream->is()) {
				$item->value = $this->parseValue();
				if (!$stream->is(TokenStream::Newline, Token::EndOfFile)) {
					$stream->throwUnexpected();
				}
			}


			$this->injectPos($res, $res->startTokenPos, $item->value->endTokenPos);
			$this->injectPos($item, $item->startTokenPos, $item->value->endTokenPos);

			while ($stream->tryConsumeNewline());
			if (!$stream->is()) {
				return $res;
			}

			$nextIndent = $stream->getIndentation();
			$indent = $this->resolve($pending, $nextIndent);
			if (!self::isComparable($nextIndent, $indent)) {
				$stream->throwUnexpected(self::InvalidIndentation);

			} elseif (strlen($nextIndent) > strlen($indent)) {
				$stream->throwUnexpected('Bad indentation');

			} elseif (strlen($nextIndent) < strlen($indent)) { // close block
				return $res;
			}
		}
	}


	private function parseValue(): Node
	{
		if ($token = $this->stream->tryConsume(Token::String)) {
			try {
				$node = new Node\StringNode(Scalars::evaluateString($token->text));
				$this->injectPos($node, $this->getIndex() - 1);
			} catch (Exception $e) {
				throw new Exception($e->getMessage(), $token->origin);
			}
		} elseif ($token = $this->stream->tryConsume(Token::Literal)) {
			$pos = $this->getIndex() - 1;
			$node = new Node\LiteralNode($this->stream->is(Token::Colon, Token::Equals) ? Scalars::literalKey($token->text) : Scalars::evaluateLiteral($token->text));
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
			$valueNode = new Node\LiteralNode(Scalars::evaluateLiteral($token->text));
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
		$depth = $this->depth;
		try {
			return $this->parseBracesItems();
		} finally {
			$this->depth = $depth;
		}
	}


	private function parseBracesItems(): Node\InlineArrayNode
	{
		$token = $this->stream->consume(Token::OpenBracket, Token::OpenBrace, Token::OpenParen);
		$this->enter($token);
		$endBrace = self::Pairs[$token->kind];
		$res = new Node\InlineArrayNode;
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


	/** One level deeper into arrays. */
	private function enter(Token $token): void
	{
		if (++$this->depth > self::MaxDepth) {
			throw new Exception(self::TooDeep, $token->origin);
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


	private static function isComparable(string $a, string $b): bool
	{
		$n = min(strlen($a), strlen($b));
		return strncmp($a, $b, $n) === 0;
	}


	/** @return int<0, max> */
	private function getIndex(): int
	{
		return $this->stream->mark()[0];
	}
}
