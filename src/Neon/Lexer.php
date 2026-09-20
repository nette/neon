<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Neon;

use function strlen;


/**
 * Splits NEON into tokens with their trivia attached; the last token is EndOfFile, and printing all tokens
 * in order gives back the input.
 * @internal
 */
final class Lexer
{
	/**
	 * The scanner as one expression. The alternatives are tried in order: a string before a literal, because
	 * a quote cannot start a literal; a literal before a char, because `:` and `-` followed by a character of a
	 * literal start one; anything else is unexpected, so that the matches always cover the whole input.
	 */
	private const Pattern = <<<'XX'
		~(?:
			^\x{FEFF} (*MARK:ByteOrderMark)
			| (?:
				# a multiline string closes at the first later line holding only spaces and the triple;
				# the line right after the opening closes it only when no later line does
				''' \r?\n [^\n]*+ \n (?: (?! (?:[ \t]|\r(?!\n))*+ ''' ) [^\n]*+ \n )*+ (?:[ \t]|\r(?!\n))*+ '''
				| ''' \r?\n (?:[ \t]|\r(?!\n))*+ '''
				| """ \r?\n [^\n]*+ \n (?: (?! (?:[ \t]|\r(?!\n))*+ """ ) [^\n]*+ \n )*+ (?:[ \t]|\r(?!\n))*+ """
				| """ \r?\n (?:[ \t]|\r(?!\n))*+ """
				| ' (?: '' | [^'\n] )*+ '
				| " (?: \\ (?! \r?\n ) [^\n] | [^"\\\n] )*+ "
			) (*MARK:String)
			| \# (?: [^\r\n] | \r(?!\n) )*+ (*MARK:Comment)
			| \r?\n (*MARK:LineEnding)
			| (?: [ \t] | \r(?!\n) )++ (*MARK:Whitespace)
			| (?:
				[^#"',:=\[\]{}()`\- \t\r\n]
				| (?<!['"]) [:\-] [^"',=\[\]{}() \t\r\n]
			)
			(?:
				[^,:=\]})( \t\r\n]++
				| : (?= [^,\]}) \t\r\n] )
				| (?: [ \t] | \r(?!\n) )++ (?= [^#,:=\]})( \t\r\n] )
			)*+ (*MARK:Literal)
			| [,:=\[\]{}()\-] (*MARK:Char)
			| [\s\S] (*MARK:Unexpected)
		)~Axu
		XX;

	private const Chars = [
		',' => Token::Comma,
		':' => Token::Colon,
		'=' => Token::Equals,
		'-' => Token::Dash,
		'[' => Token::OpenBracket,
		']' => Token::CloseBracket,
		'{' => Token::OpenBrace,
		'}' => Token::CloseBrace,
		'(' => Token::OpenParen,
		')' => Token::CloseParen,
	];

	private const TriviaKinds = [
		'Whitespace' => Trivia::Whitespace,
		'LineEnding' => Trivia::LineEnding,
		'Comment' => Trivia::Comment,
		'ByteOrderMark' => Trivia::ByteOrderMark,
	];

	/** One well-formed UTF-8 sequence, matched on bytes. */
	private const Utf8Char = '[\x00-\x7F]|[\xC2-\xDF][\x80-\xBF]|\xE0[\xA0-\xBF][\x80-\xBF]|[\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}'
		. '|\xED[\x80-\x9F][\x80-\xBF]|\xF0[\x90-\xBF][\x80-\xBF]{2}|[\xF1-\xF3][\x80-\xBF]{3}|\xF4[\x80-\x8F][\x80-\xBF]{2}';


	/**
	 * @return non-empty-list<Token>
	 * @throws Exception  for input that is not NEON
	 * @throws \RuntimeException  when PCRE fails, which says nothing about the input
	 */
	public function tokenize(string $input, bool $origin = true): array
	{
		if (!preg_match('##u', $input)) {
			preg_match('~(?:' . self::Utf8Char . ')*+~A', $input, $m);
			throw new Exception('Invalid UTF-8 sequence.', new Position()->advance($m[0] ?? ''));
		}

		preg_match_all(self::Pattern, $input, $matches, PREG_SET_ORDER);
		if (preg_last_error() !== PREG_NO_ERROR) {
			throw new \RuntimeException('The lexer failed: ' . preg_last_error_msg());
		}

		$ascii = !preg_match('~[\x80-\xFF]~', $input);
		$line = 1;
		$column = 1;
		$offset = 0;
		$byte = 0;
		$tokens = [];
		$pending = []; // trivia not yet attached
		$last = null; // the last token, not made yet: [kind, text, leading, trailing, position]
		$lineEnded = false; // whether the trailing trivia of the last token hold a line ending
		$shared = []; // trivia are immutable, one instance serves equal texts of different tokens

		foreach ($matches as $match) {
			$text = $match[0];
			$mark = $match['MARK'];
			if ($mark === 'Unexpected') {
				throw new Exception("Unexpected '" . self::quote(substr($input, $byte)) . "'", new Position($line, $column, $offset));

			} elseif ($mark === 'String' || $mark === 'Literal' || $mark === 'Char') {
				if ($last) {
					$tokens[] = new Token(...$last);
				}
				$kind = match ($mark) {
					'String' => Token::String,
					'Literal' => Token::Literal,
					default => self::Chars[$text],
				};
				$last = [$kind, $text, $pending, [], $origin ? new Position($line, $column, $offset) : null];
				$pending = [];
				$lineEnded = false;

			} else {
				$trivia = $shared[$text] ??= new Trivia(self::TriviaKinds[$mark], $text);
				if ($last && !$pending && !$lineEnded) { // trailing trivia run up to and including the first line ending
					$last[3][] = $trivia;
					$lineEnded = $mark === 'LineEnding';
				} else { // a list holds an instance once, so that replaceTrivia() finds the one meant
					$pending[] = in_array($trivia, $pending, true) ? new Trivia($trivia->kind, $text) : $trivia;
				}
			}

			$byte += strlen($text);
			$length = $ascii ? strlen($text) : mb_strlen($text, 'UTF-8');
			$offset += $length;
			if ($mark === 'LineEnding') {
				$line++;
				$column = 1;
			} elseif ($mark === 'String' && ($lines = substr_count($text, "\n"))) {
				$line += $lines;
				$tail = substr($text, strrpos($text, "\n") + 1);
				$column = ($ascii ? strlen($tail) : mb_strlen($tail, 'UTF-8')) + 1;
			} else {
				$column += $length;
			}
		}

		if ($byte !== strlen($input)) {
			throw new \LogicException("The lexer stopped at byte $byte of " . strlen($input) . '.');
		} elseif ($last) {
			$tokens[] = new Token(...$last);
		}
		$tokens[] = new Token(Token::EndOfFile, '', $pending, [], $origin ? new Position($line, $column, $offset) : null);
		return $tokens;
	}


	/** Whether the scanner reads the whole text, which is valid UTF-8, as one literal. */
	public static function isLiteral(string $text): bool
	{
		return preg_match(self::Pattern, $text, $m) && $m['MARK'] === 'Literal' && strlen($m[0]) === strlen($text);
	}


	/** Text quoted by `Unexpected '...'`: cut to 40 code points, a line ending counting as one, shown as <new line>. */
	public static function quote(string $text): string
	{
		return str_replace("\n", '<new line>', mb_substr(str_replace("\r\n", "\n", $text), 0, 40, 'UTF-8'));
	}
}
