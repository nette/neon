<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Neon;

use function count, in_array, min;


/**
 * The tokens as the parser reads them: line endings are trivia, but before a token whose preceding trivia
 * hold one stands a virtual Newline, which is consumed once.
 * @internal
 */
final class TokenStream
{
	/** The kind of the virtual line break, next to the kinds of Token. */
	public const Newline = 0;

	/** @var int<0, max> */
	private int $index = 0;
	private bool $newlineTaken = false;

	/** @var list<bool> whether a line break stands before the token of the index */
	private array $newlines = [];


	public function __construct(
		/** @var non-empty-list<Token> ending with EndOfFile */
		public readonly array $tokens,
	) {
		foreach ($tokens as $i => $token) {
			$this->newlines[] = self::hasLineEnding($i === 0 ? $token->leadingTrivia : $tokens[$i - 1]->trailingTrivia);
		}
	}


	/** The kind of the next item: a kind of Token or Newline. */
	public function peek(): int
	{
		return !$this->newlineTaken && $this->newlines[$this->index]
			? self::Newline
			: $this->tokens[$this->index]->kind;
	}


	/** Whether the next item is of one of the kinds; without kinds, whether anything but the end follows. */
	public function is(int ...$kinds): bool
	{
		$next = $this->peek();
		return $kinds ? in_array($next, $kinds, true) : $next !== Token::EndOfFile;
	}


	/**
	 * Consumes a Newline if one is next.
	 * @phpstan-impure
	 */
	public function tryConsumeNewline(): bool
	{
		if ($this->peek() === self::Newline) {
			$this->newlineTaken = true;
			return true;
		}
		return false;
	}


	/**
	 * Consumes the next token if it is of one of the kinds, or of any kind but EndOfFile without kinds.
	 * @phpstan-impure
	 */
	public function tryConsume(int ...$kinds): ?Token
	{
		$next = $this->peek();
		if ($next === self::Newline || $next === Token::EndOfFile || ($kinds && !in_array($next, $kinds, true))) {
			return null;
		}
		$this->newlineTaken = false;
		return $this->tokens[$this->index++];
	}


	/**
	 * Consumes the next token of one of the kinds, or of any kind but EndOfFile without kinds.
	 * @throws Exception  when something else is next
	 * @phpstan-impure
	 */
	public function consume(int ...$kinds): Token
	{
		return $this->tryConsume(...$kinds) ?? $this->throwUnexpected();
	}


	/** @return array{int<0, max>, bool} */
	public function mark(): array
	{
		return [$this->index, $this->newlineTaken];
	}


	/** @param  array{int<0, max>, bool}  $mark */
	public function reset(array $mark): void
	{
		[$this->index, $this->newlineTaken] = $mark;
	}


	/** The indentation of the next token if it starts a line: the whitespace right after a line ending or the start. */
	public function getIndentation(): string
	{
		$i = $this->index;
		$leading = $this->tokens[$i]->leadingTrivia;
		$trailing = $i > 0 ? $this->tokens[$i - 1]->trailingTrivia : [];
		$n = count($leading);
		[$last, $before] = $n
			? [$leading[$n - 1], $leading[$n - 2] ?? $trailing[count($trailing) - 1] ?? null]
			: [$trailing[count($trailing) - 1] ?? null, $trailing[count($trailing) - 2] ?? null];
		return $last?->kind === Trivia::Whitespace
			&& ($before === null ? $i === 0 : $before->kind === Trivia::LineEnding || $before->kind === Trivia::ByteOrderMark)
				? $last->text
				: '';
	}


	/**
	 * Throws the error at the next item, `Unexpected ...` unless the description is given.
	 * @throws Exception
	 */
	public function throwUnexpected(?string $description = null): never
	{
		$next = $this->peek();
		if ($next === self::Newline) {
			[$position, $count] = $this->locateNewline();
			throw new Exception($description ?? "Unexpected '" . str_repeat('<new line>', min($count, 40)) . "'", $position);
		}

		$token = $this->tokens[$this->index];
		throw new Exception(
			$description ?? ($next === Token::EndOfFile ? 'Unexpected end' : "Unexpected '" . Lexer::quote($token->text) . "'"),
			$token->origin,
		);
	}


	/** @param  list<Trivia>  $trivia */
	private static function hasLineEnding(array $trivia): bool
	{
		return array_any($trivia, fn($t) => $t->kind === Trivia::LineEnding);
	}


	/**
	 * The position of the line break before the next token, and how many line endings follow one another there.
	 * @return array{?Position, int}
	 */
	private function locateNewline(): array
	{
		$i = $this->index;
		$prev = $this->tokens[$i - 1] ?? null;
		$position = $prev ? $prev->origin?->advance($prev->text) : new Position;
		$trivia = [...$prev->trailingTrivia ?? [], ...$this->tokens[$i]->leadingTrivia];
		$k = 0;
		while ($trivia[$k]->kind !== Trivia::LineEnding) {
			$position = $position?->advance($trivia[$k++]->text);
		}
		$count = 0;
		while (($trivia[$k + $count] ?? null)?->kind === Trivia::LineEnding) {
			$count++;
		}
		return [$position, $count];
	}
}
