<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Neon;

use Nette\Neon\Nodes\DocumentNode;
use function count;


/**
 * A token of the source with the trivia around it: the leading trivia up to the line ending before it,
 * the trailing trivia up to and including the first line ending after it.
 */
final class Token implements \Stringable
{
	public const
		String = 1,
		Literal = 2,
		Colon = 3,
		Equals = 4,
		Comma = 5,
		Dash = 6,
		OpenBracket = 7,
		CloseBracket = 8,
		OpenBrace = 9,
		CloseBrace = 10,
		OpenParen = 11,
		CloseParen = 12,
		EndOfFile = 13;

	/** The names of the kinds, as the dump writes them. */
	public const Names = [
		self::String => 'String',
		self::Literal => 'Literal',
		self::Colon => 'Colon',
		self::Equals => 'Equals',
		self::Comma => 'Comma',
		self::Dash => 'Dash',
		self::OpenBracket => 'OpenBracket',
		self::CloseBracket => 'CloseBracket',
		self::OpenBrace => 'OpenBrace',
		self::CloseBrace => 'CloseBrace',
		self::OpenParen => 'OpenParen',
		self::CloseParen => 'CloseParen',
		self::EndOfFile => 'EndOfFile',
	];

	/** @var list<Trivia> */
	public private(set) array $leadingTrivia;

	/** @var list<Trivia> */
	public private(set) array $trailingTrivia;

	/** The node the token belongs to; only the tree writes it. */
	public private(set) ?Node $parent = null;


	/**
	 * @param  list<Trivia>  $leadingTrivia
	 * @param  list<Trivia>  $trailingTrivia
	 * @internal the lexer makes the tokens as it reads them, unchecked; elsewhere a token is made by fromText()
	 */
	public function __construct(
		/** One of the constants of the class, by which it is told apart from the text in `is()`. */
		public readonly int $kind,
		public private(set) string $text,
		array $leadingTrivia = [],
		array $trailingTrivia = [],
		/** Where the token stood in the source it was read from; null for a token made otherwise. */
		public readonly ?Position $origin = null,
	) {
		$this->leadingTrivia = $leadingTrivia;
		$this->trailingTrivia = $trailingTrivia;
	}


	/** The token the scanner reads the text as; the empty text is EndOfFile, anything but one token is refused. */
	public static function fromText(string $text): self
	{
		return self::scan($text) ?? throw new \InvalidArgumentException(var_export($text, true) . ' is not one token.');
	}


	/** Replaces the text by another one of the same kind; whether the document still means the same is up to the caller. */
	public function setText(string $text): static
	{
		if (self::scan($text)?->kind !== $this->kind) {
			throw new \InvalidArgumentException(var_export($text, true) . " is not one token of kind {$this->kindName()}.");
		}
		if (Journal::isRecording()) {
			$old = $this->text;
			Journal::record(function () use ($old): void { $this->text = $old; });
		}
		$this->text = $text;
		return $this;
	}


	/**
	 * Sets the leading trivia in the canonical form; in a document they also have to fit the token before.
	 * @param  list<Trivia>  $trivia
	 */
	public function setLeadingTrivia(array $trivia): static
	{
		self::checkTrivia($trivia, leading: true, endOfFile: $this->kind === self::EndOfFile);
		[$before] = $this->findNeighbours();
		if ($before && $trivia && !self::hasLineEnding($before->trailingTrivia)) {
			throw new \InvalidArgumentException('Leading trivia must follow a line ending; on the same line they are the trailing trivia of the token before.');
		} elseif ($before && $trivia && $trivia[0]->kind === Trivia::ByteOrderMark) {
			throw new \InvalidArgumentException('A byte order mark may only stand at the start of the input.');
		}
		$this->assignTrivia('leadingTrivia', $trivia);
		return $this;
	}


	/**
	 * Sets the trailing trivia in the canonical form; in a document they also have to fit the token after.
	 * @param  list<Trivia>  $trivia
	 */
	public function setTrailingTrivia(array $trivia): static
	{
		self::checkTrivia($trivia, leading: false, endOfFile: $this->kind === self::EndOfFile);
		[, $after] = $this->findNeighbours();
		if ($after && $after->leadingTrivia && !self::hasLineEnding($trivia)) {
			throw new \InvalidArgumentException('Trailing trivia without a line ending must not be followed by leading trivia.');
		} elseif ($after && $after->kind !== self::EndOfFile && $trivia && end($trivia)->kind === Trivia::Comment) {
			throw new \InvalidArgumentException('A comment must be followed by a line ending.');
		}
		$this->assignTrivia('trailingTrivia', $trivia);
		return $this;
	}


	/**
	 * Writes the leading trivia in the canonical form, whatever the token before them.
	 * @param  list<Trivia>  $trivia
	 * @internal only the builder and the editor write through it, and see to both sides of a line themselves
	 */
	public function writeLeadingTrivia(array $trivia): void
	{
		self::checkTrivia($trivia, leading: true, endOfFile: $this->kind === self::EndOfFile);
		$this->assignTrivia('leadingTrivia', $trivia);
	}


	/**
	 * Writes the trailing trivia in the canonical form, whatever the token after them.
	 * @param  list<Trivia>  $trivia
	 * @internal only the builder and the editor write through it, and see to both sides of a line themselves
	 */
	public function writeTrailingTrivia(array $trivia): void
	{
		self::checkTrivia($trivia, leading: false, endOfFile: $this->kind === self::EndOfFile);
		$this->assignTrivia('trailingTrivia', $trivia);
	}


	/**
	 * Writes the parent as it is and notes in the journal how to undo it.
	 * @internal only the tree writes it: the constructors of the nodes, the clone, the builder and the editor
	 */
	public function writeParent(?Node $parent): void
	{
		if (Journal::isRecording()) {
			$old = $this->parent;
			Journal::record(function () use ($old): void { $this->parent = $old; });
		}
		$this->parent = $parent;
	}


	/** Replaces a trivia, found by identity, in the leading or the trailing trivia. */
	public function replaceTrivia(Trivia $old, Trivia $new): static
	{
		$replace = fn(Trivia $trivia) => $trivia === $old ? $new : $trivia;
		if (in_array($old, $this->leadingTrivia, true)) {
			return $this->setLeadingTrivia(array_map($replace, $this->leadingTrivia));
		} elseif (in_array($old, $this->trailingTrivia, true)) {
			return $this->setTrailingTrivia(array_map($replace, $this->trailingTrivia));
		}
		throw new \InvalidArgumentException('The trivia does not belong to the token.');
	}


	/** Whether the token is of one of the kinds, or has one of the texts. */
	public function is(int|string ...$what): bool
	{
		return in_array($this->kind, $what, true) || in_array($this->text, $what, true);
	}


	/** The current position, computed by a walk from the root; null in a subtree without a document. */
	public function getPosition(): ?Position
	{
		$root = $this->findDocument();
		if (!$root) {
			return null;
		}

		$text = '';
		foreach ($root->getTokens() as $token) {
			if ($token === $this) {
				foreach ($this->leadingTrivia as $trivia) {
					$text .= $trivia->text;
				}
				return new Position()->advance($text);
			}
			$text .= $token;
		}
		return null;
	}


	/** The source text of the token with its trivia. */
	public function __toString(): string
	{
		$res = '';
		foreach ($this->leadingTrivia as $trivia) {
			$res .= $trivia->text;
		}
		$res .= $this->text;
		foreach ($this->trailingTrivia as $trivia) {
			$res .= $trivia->text;
		}
		return $res;
	}


	/** A copy without a parent; the text, the trivia and the origin are the same. */
	public function __clone()
	{
		$this->parent = null;
	}


	/** @return never */
	public function __serialize(): array
	{
		throw new \LogicException('A token cannot be serialized; serialize the text of the document instead.');
	}


	/** @param  list<Trivia>  $trivia */
	private function assignTrivia(string $side, array $trivia): void
	{
		$seen = [];
		foreach ($trivia as $i => $t) { // an instance stands in a list once, so that replaceTrivia() finds the one meant
			if (isset($seen[$id = spl_object_id($t)])) {
				$trivia[$i] = new Trivia($t->kind, $t->text);
			}
			$seen[$id] = true;
		}
		if (Journal::isRecording()) {
			$old = $this->$side;
			Journal::record(function () use ($side, $old): void { $this->$side = $old; });
		}
		$this->$side = $trivia;
	}


	private function kindName(): string
	{
		return self::Names[$this->kind];
	}


	private static function scan(string $text): ?self
	{
		try {
			$tokens = new Lexer()->tokenize($text, origin: false);
		} catch (Exception) {
			return null;
		}
		return match (true) {
			$text === '' => $tokens[0],
			count($tokens) === 2 && !$tokens[0]->leadingTrivia && !$tokens[0]->trailingTrivia && !$tokens[1]->leadingTrivia => $tokens[0],
			default => null,
		};
	}


	/**
	 * The rules of the canonical form that a list of trivia tells by itself.
	 * @param  list<Trivia>  $list
	 */
	private static function checkTrivia(array $list, bool $leading, bool $endOfFile): void
	{
		if (!array_is_list($list)) {
			throw new \InvalidArgumentException('Trivia are given as a list.');
		}
		$list = array_map(static fn(Trivia $trivia) => $trivia, $list); // the type of each one checked
		if (!$leading && $endOfFile && $list) {
			throw new \InvalidArgumentException('EndOfFile has no trailing trivia.');
		}
		foreach ($list as $i => $trivia) {
			$next = $list[$i + 1] ?? null;
			if ($trivia->kind === Trivia::Whitespace && $next?->kind === Trivia::Whitespace) {
				throw new \InvalidArgumentException('Two whitespace trivia must not stand next to each other.');
			} elseif ($trivia->kind === Trivia::ByteOrderMark && (!$leading || $i > 0)) {
				throw new \InvalidArgumentException('A byte order mark may only be the first leading trivia.');
			} elseif ($trivia->kind === Trivia::LineEnding && !$leading && $next) {
				throw new \InvalidArgumentException('A line ending must be the last of the trailing trivia.');
			} elseif (
				$trivia->kind === Trivia::Comment
				&& $next?->kind !== Trivia::LineEnding
				&& ($next || ($leading && !$endOfFile))
			) {
				throw new \InvalidArgumentException('A comment must be followed by a line ending.');
			} elseif (str_ends_with($trivia->text, "\r") && $next && str_starts_with($next->text, "\n")) {
				throw new \InvalidArgumentException('A carriage return before a line feed would make one line ending of the two.');
			}
		}
	}


	/** @param  list<Trivia>  $trivia */
	private static function hasLineEnding(array $trivia): bool
	{
		return array_any($trivia, fn($t) => $t->kind === Trivia::LineEnding);
	}


	private function findDocument(): ?DocumentNode
	{
		$root = $this->parent;
		while ($root?->parent) {
			$root = $root->parent;
		}
		return $root instanceof DocumentNode ? $root : null;
	}


	/** @return array{?self, ?self} the tokens before and after this one in a document; none outside one */
	private function findNeighbours(): array
	{
		return $this->findDocument()
			? [$this->findNeighbour(backward: true), $this->findNeighbour(backward: false)]
			: [null, null];
	}


	/**
	 * The token of the tree right before or after this one.
	 * @internal
	 */
	public function findNeighbour(bool $backward): ?self
	{
		foreach ($this->iterateNeighbours($backward) as $token) {
			return $token;
		}
		return null;
	}


	/**
	 * The tokens of the tree before or after this one, the nearest first, read lazily from the nodes around it.
	 * @return \Generator<self>
	 * @internal
	 */
	public function iterateNeighbours(bool $backward): \Generator
	{
		$child = $this;
		for ($node = $this->parent; $node; $child = $node, $node = $node->parent) {
			$children = $node->getChildren();
			$i = (int) array_search($child, $children, true);
			foreach ($backward ? array_reverse(array_slice($children, 0, $i)) : array_slice($children, $i + 1) as $sibling) {
				yield from self::iterateTokens($sibling, $backward);
			}
		}
	}


	/** @return \Generator<self> */
	private static function iterateTokens(Node|self $node, bool $backward): \Generator
	{
		if ($node instanceof self) {
			yield $node;
			return;
		}
		$children = $node->getChildren();
		foreach ($backward ? array_reverse($children) : $children as $child) {
			yield from self::iterateTokens($child, $backward);
		}
	}
}
