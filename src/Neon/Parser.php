<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Neon;

use Nette\Neon\Nodes\{
	ArrayItemNode, BlockArrayNode, DocumentNode, EntityChainNode, EntityNode, InlineArrayNode, LiteralNode, StringNode, ValueNode,
};
use function count, is_string, strlen;


/**
 * The parser of nette/neon 3.x on tokens with trivia, reading line breaks as the virtual Newline of the stream.
 * @internal
 */
final class Parser
{
	public const MaxDepth = 500;

	private const Pairs = [
		Token::OpenBracket => Token::CloseBracket,
		Token::OpenBrace => Token::CloseBrace,
		Token::OpenParen => Token::CloseParen,
	];

	private TokenStream $stream;
	private int $depth = 0;
	private bool $tooDeep = false;


	/**
	 * @throws Exception  for input that is not NEON
	 */
	public function parse(string $input, bool $origin = true): DocumentNode
	{
		$this->stream = $stream = new TokenStream(new Lexer()->tokenize($input, $origin));
		$this->depth = 0;
		$this->tooDeep = false;

		while ($stream->tryConsumeNewline());
		$value = $this->parseBlock($stream->getIndentation());

		while ($stream->tryConsumeNewline());
		if ($stream->is()) {
			$stream->throwUnexpected();
		}

		$document = new DocumentNode($value, $stream->tokens[count($stream->tokens) - 1]);
		$this->verify($document);
		return $document;
	}


	/** @param  string|\Closure(string): string  $indent */
	private function parseBlock(string|\Closure $indent, bool $onlyBullets = false): ?ValueNode
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
	 * compared starts with one, by two spaces otherwise.
	 * @param  string|\Closure(string): string  $base
	 */
	private function parseDashBlock(string|\Closure $base): ?ValueNode
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
			if (!$this->tooDeep && $resolved !== null && str_ends_with($resolved, "\t") && $line) {
				$this->stream->reset($line);
				$this->stream->throwUnexpected('Invalid combination of tabs and spaces');
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
	private function parseBlockItems(string|\Closure $pending, bool $onlyBullets): ?ValueNode
	{
		$stream = $this->stream;
		$items = [];
		$keys = self::createKeys();

		while (true) {
			$bullet = $stream->tryConsume(Token::Dash);
			$key = $colon = $value = null;

			if ($bullet) {
				if (!$items) {
					$this->enter($bullet);
				}
			} elseif (!$stream->is() || $onlyBullets) {
				return $items ? new BlockArrayNode($items) : null;
			} else {
				$head = $this->parseValue();
				if ($colon = $stream->tryConsume(Token::Colon, Token::Equals)) {
					$key = $this->addKey($keys, $head);
					if (!$items) {
						$this->enter($key->token);
					}
				} else {
					if ($items) {
						$stream->throwUnexpected();
					}
					return $head;
				}
			}

			if ($stream->tryConsumeNewline()) {
				while ($stream->tryConsumeNewline());
				$nextIndent = $stream->getIndentation();
				$indent = $this->resolve($pending, $nextIndent);
				if (!self::isComparable($nextIndent, $indent)) {
					$stream->throwUnexpected('Invalid combination of tabs and spaces');
				} elseif (strlen($nextIndent) > strlen($indent)) { // open new block
					$value = $this->parseBlock($nextIndent);
				} elseif (strlen($nextIndent) < strlen($indent)) { // close block
					$items[] = $this->createItem($keys, $bullet, $key, $colon, null);
					return new BlockArrayNode($items);
				} elseif ($key !== null && $stream->is(Token::Dash)) { // special dash subblock
					$value = $this->parseBlock($indent, onlyBullets: true);
				}

			} elseif ($key === null) { // open new block after dash
				$value = $this->parseDashBlock($pending);

			} elseif ($stream->is()) {
				$value = $this->parseValue();
				if (!$stream->is(TokenStream::Newline, Token::EndOfFile)) {
					$stream->throwUnexpected();
				}
			}

			$items[] = $this->createItem($keys, $bullet, $key, $colon, $value);

			while ($stream->tryConsumeNewline());
			if (!$stream->is()) {
				return new BlockArrayNode($items);
			}

			$nextIndent = $stream->getIndentation();
			$indent = $this->resolve($pending, $nextIndent);
			if (!self::isComparable($nextIndent, $indent)) {
				$stream->throwUnexpected('Invalid combination of tabs and spaces');
			} elseif (strlen($nextIndent) > strlen($indent)) {
				$stream->throwUnexpected('Bad indentation');
			} elseif (strlen($nextIndent) < strlen($indent)) { // close block
				return new BlockArrayNode($items);
			}
		}
	}


	private function parseValue(): ValueNode
	{
		$stream = $this->stream;
		switch ($stream->peek()) {
			case Token::String:
				$token = $stream->consume();
				try {
					Scalars::evaluateString($token->text);
				} catch (Exception $e) {
					throw new Exception($e->getDescription(), $token->origin);
				}
				$node = new StringNode($token);
				break;
			case Token::Literal:
				$node = new LiteralNode($stream->consume());
				break;
			case Token::OpenBracket:
			case Token::OpenParen:
			case Token::OpenBrace:
				$node = $this->parseBraces();
				break;
			default:
				$stream->throwUnexpected();
		}

		return $stream->peek() === Token::OpenParen ? $this->parseEntity($node) : $node;
	}


	/** An entity or a chain whose head is the node, followed by an opening parenthesis. */
	private function parseEntity(StringNode|LiteralNode|InlineArrayNode $node): ValueNode
	{
		$stream = $this->stream;
		$this->checkEntityHead($node);
		$entities = [new EntityNode($node, $this->parseBraces())];
		while ($token = $stream->tryConsume(Token::Literal)) {
			$head = new LiteralNode($token);
			$this->checkEntityHead($head);
			if ($stream->is(Token::OpenParen)) {
				$entities[] = new EntityNode($head, $this->parseBraces());
			} else {
				$entities[] = new EntityNode($head, null);
				break;
			}
		}

		return count($entities) === 1
			? $entities[0]
			: new EntityChainNode($entities);
	}


	private function parseBraces(): InlineArrayNode
	{
		$depth = $this->depth;
		try {
			return $this->parseBracesItems();
		} finally {
			$this->depth = $depth;
		}
	}


	private function parseBracesItems(): InlineArrayNode
	{
		$stream = $this->stream;
		$open = $stream->consume(Token::OpenBracket, Token::OpenBrace, Token::OpenParen);
		$this->enter($open);
		$end = self::Pairs[$open->kind];
		$items = [];
		$keys = self::createKeys();
		$next = null; // the key and the colon of the next item, read on the line after a key without a value

		while (true) {
			if ($next) {
				[$head, $colon] = $next;
				$next = null;
			} else {
				while ($stream->tryConsumeNewline());
				if ($close = $stream->tryConsume($end)) {
					return new InlineArrayNode($open, $items, $close);
				}
				$head = $this->parseValue();
				$colon = $this->tryConsumeColon();
			}

			$value = $comma = null;
			if ($colon) {
				$key = $this->addKey($keys, $head);
				$lineEnd = $stream->tryConsumeNewline();
				if (!$stream->is(Token::Comma, $end)) {
					$value = $this->parseValue();
					if ($lineEnd && ($nextColon = $this->tryConsumeColon())) {
						$next = [$value, $nextColon];
						$value = null;
					} elseif ($lineEnd && ($origin = $value->getFirstToken()->origin)) { // a transitional notice for documents written for 3.x
						$name = $head->getFirstToken()->text;
						trigger_error(
							"Neon: the value on line $origin->line after key '$name' is the value of that key since nette/neon 4.0.1; nette/neon 3 read the key as null and the value as an item of its own.",
							E_USER_NOTICE,
						);
					}
				}
			} else {
				$key = null;
				$value = $head;
			}

			$before = $stream->mark();
			if (!$next) {
				while ($stream->tryConsumeNewline());
				$comma = $stream->tryConsume(Token::Comma);
			}
			$items[] = $this->createItem($keys, null, $key, $colon, $value, $comma);
			if (!$next && $before === $stream->mark() && !$stream->is($end)) {
				$stream->throwUnexpected();
			}
		}
	}


	/** The colon or the equals sign after a key in an inline array, which may stand on a later line. */
	private function tryConsumeColon(): ?Token
	{
		$mark = $this->stream->mark();
		while ($this->stream->tryConsumeNewline());
		$colon = $this->stream->tryConsume(Token::Colon, Token::Equals);
		if (!$colon) {
			$this->stream->reset($mark);
		}
		return $colon;
	}


	/** One level deeper into arrays. */
	private function enter(Token $token): void
	{
		if (++$this->depth > self::MaxDepth) {
			$this->tooDeep = true;
			throw new Exception('Too deep nesting', $token->origin);
		}
	}


	private function checkEntityHead(ValueNode $node): void
	{
		$name = match (true) {
			$node instanceof StringNode => Scalars::evaluateString($node->token->text),
			$node instanceof LiteralNode => $node->token->text,
			default => null,
		};
		if ($name === Neon::Chain) {
			throw new Exception("Entity name '" . Neon::Chain . "' is reserved", $node->getFirstToken()->origin);
		}
	}


	/** Every token of the stream is in the tree exactly once, in the order of the stream. */
	private function verify(DocumentNode $document): void
	{
		if ($document->getTokens() !== $this->stream->tokens) {
			throw new \LogicException('Internal error: the tree does not hold the tokens of the input in order.');
		}
	}


	/**
	 * The explicit keys seen in one array, its greatest integer key so far and the last key added.
	 * @return array{seen: array<string, true>, max: ?string, last: string}
	 */
	private static function createKeys(): array
	{
		return ['seen' => [], 'max' => null, 'last' => ''];
	}


	/**
	 * Checks an explicit key for duplicates.
	 * @param  array{seen: array<string, true>, max: ?string, last: string}  $keys
	 */
	private function addKey(array &$keys, ValueNode $node): StringNode|LiteralNode
	{
		if (!$node instanceof StringNode && !$node instanceof LiteralNode) {
			throw new Exception('Unacceptable key', $node->getFirstToken()->origin);
		}

		$key = $node instanceof StringNode
			? Scalars::evaluateString($node->token->text)
			: Scalars::literalKey($node->token->text);
		if (isset($keys['seen'][$key])) {
			throw new Exception("Duplicated key '$key'", $node->token->origin);
		}
		$keys['seen'][$key] = true;
		$keys['last'] = $key;
		return $node;
	}


	/**
	 * Makes the item and accounts its key, explicit or implicit.
	 * @param  array{seen: array<string, true>, max: ?string, last: string}  $keys
	 */
	private function createItem(
		array &$keys,
		?Token $bullet,
		StringNode|LiteralNode|null $key,
		?Token $colon,
		?ValueNode $value,
		?Token $separator = null,
	): ArrayItemNode
	{
		$item = new ArrayItemNode($bullet, $key, $colon, $value, $separator);
		$keys['max'] = $key
			? Scalars::maxIntegerKey($keys['max'], $keys['last']) // the key addKey() has seen
			: Scalars::nextImplicitKey($keys['max']) ?? throw new Exception(
				'Implicit key overflow',
				($value ?? $item)->getFirstToken()->origin,
			);
		return $item;
	}


	private static function isComparable(string $a, string $b): bool
	{
		$n = min(strlen($a), strlen($b));
		return strncmp($a, $b, $n) === 0;
	}
}
