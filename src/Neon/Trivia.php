<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Neon;


/**
 * Whitespace, a line ending, a comment or a byte order mark around a token. It never changes once made,
 * so one instance may sit on several tokens.
 */
final readonly class Trivia
{
	public const
		Whitespace = 1,
		LineEnding = 2,
		Comment = 3,
		ByteOrderMark = 4;

	/** The names of the kinds, as the dump writes them. */
	public const Names = [
		self::Whitespace => 'Whitespace',
		self::LineEnding => 'LineEnding',
		self::Comment => 'Comment',
		self::ByteOrderMark => 'ByteOrderMark',
	];


	public function __construct(
		/** One of the constants of the class. */
		public int $kind,
		public string $text,
	) {
		if (self::classify($text) !== $kind) {
			throw new \InvalidArgumentException(var_export($text, true) . ' is not ' . (self::Names[$kind] ?? 'a kind of trivia') . '.');
		}
	}


	/** The trivia the text is: one run of spaces and tabs, one line ending, one comment, or a byte order mark. */
	public static function fromText(string $text): self
	{
		$kind = self::classify($text) ?? throw new \InvalidArgumentException(var_export($text, true) . ' is not one trivia.');
		return new self($kind, $text);
	}


	/** A trivia of the same kind with another text. */
	public function withText(string $text): self
	{
		return new self($this->kind, $text);
	}


	public function is(int ...$kinds): bool
	{
		return in_array($this->kind, $kinds, true);
	}


	/** The text of a comment without `#`, trimmed; an empty string for other trivia. */
	public function getCommentText(): string
	{
		return $this->kind === self::Comment ? trim(substr($this->text, 1)) : '';
	}


	private static function classify(string $text): ?int
	{
		return match (true) {
			(bool) preg_match('~^(?:[ \t]|\r(?!\n))++$~D', $text) => self::Whitespace,
			$text === "\n" || $text === "\r\n" => self::LineEnding,
			(bool) preg_match('~^\#(?:[^\r\n]|\r(?!\n))*+$~Du', $text) => self::Comment,
			$text === "\u{FEFF}" => self::ByteOrderMark,
			default => null,
		};
	}
}
