<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Neon;


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


	/**
	 * @param  list<Trivia>  $leadingTrivia
	 * @param  list<Trivia>  $trailingTrivia
	 * @internal the lexer makes the tokens as it reads them, unchecked
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


	/** Whether the token is of one of the kinds, or has one of the texts. */
	public function is(int|string ...$what): bool
	{
		return in_array($this->kind, $what, true) || in_array($this->text, $what, true);
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
}
