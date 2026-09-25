<?php declare(strict_types=1);

/**
 * Text form of the tree, as the corpora cst/ and roundtrip/ write it.
 */

namespace Tests;

use Nette\Neon\Node;
use Nette\Neon\Token;
use Nette\Neon\Trivia;


final class Dumper
{
	private const ShortEscapes = ["\x08" => '\b', "\f" => '\f', "\n" => '\n', "\r" => '\r', "\t" => '\t', '"' => '\"', '\\' => '\\\\'];


	public static function dump(Node $node): string
	{
		$lines = [];
		self::write($node, '', 0, $lines);
		return implode("\n", $lines) . "\n";
	}


	public static function dumpToken(Token $token): string
	{
		$res = Token::Names[$token->kind] . ' ' . self::dumpText($token->text);
		if ($token->leadingTrivia) {
			$res .= ' <' . self::dumpTrivia($token->leadingTrivia);
		}
		if ($token->trailingTrivia) {
			$res .= ' >' . self::dumpTrivia($token->trailingTrivia);
		}
		return $res;
	}


	/** @param  list<string>  $lines */
	private static function write(Node $node, string $prefix, int $depth, array &$lines): void
	{
		$indent = str_repeat("\t", $depth);
		$lines[] = $indent . $prefix . substr(strrchr('\\' . $node::class, '\\') ?: '', 1);
		foreach ($node::Slots as $slot) {
			$value = $node->$slot;
			if ($value === null) {
				continue;
			} elseif (is_array($value)) {
				if (!$value) {
					$lines[] = "$indent\t$slot: []";
					continue;
				}
				$lines[] = "$indent\t$slot:";
				foreach ($value as $item) {
					if ($item instanceof Node) {
						self::write($item, '- ', $depth + 2, $lines);
					}
				}
			} elseif ($value instanceof Node) {
				self::write($value, $slot . ': ', $depth + 1, $lines);
			} elseif ($value instanceof Token) {
				$lines[] = "$indent\t$slot: " . self::dumpToken($value);
			}
		}
	}


	/** @param  list<Trivia>  $list */
	private static function dumpTrivia(array $list): string
	{
		return '[' . implode(', ', array_map(fn(Trivia $trivia) => Trivia::Names[$trivia->kind] . ' ' . self::dumpText($trivia->text), $list)) . ']';
	}


	/** JSON string escaping only quotes, backslashes and control characters. */
	private static function dumpText(string $text): string
	{
		return '"' . preg_replace_callback(
			'~[\x00-\x1F"\\\\]~',
			fn(array $m) => self::ShortEscapes[$m[0]] ?? sprintf('\u%04x', ord($m[0])),
			$text,
		) . '"';
	}
}
