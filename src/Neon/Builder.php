<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Neon;

use Nette\Neon\Nodes\ArrayItemNode;
use Nette\Neon\Nodes\BlockArrayNode;
use Nette\Neon\Nodes\ValueNode;
use function array_key_exists, is_array, is_int, is_object, is_string;


/**
 * Makes nodes from values of PHP. The text is what the encoder writes, so new values look the same as encoded
 * ones; it is parsed, so the nodes are valid by construction.
 * @internal
 */
final class Builder
{
	/**
	 * A value as a node, without edge trivia; a non-empty array is a block if the place allows it.
	 * @param  string  $indent  the indentation of the line of the item that will hold the value
	 * @param  string  $unit  the indentation unit of nested blocks
	 */
	public static function buildValue(
		mixed $value,
		bool $block,
		string $indent,
		string $unit,
		string $eol,
		bool $doubleQuoted = false,
	): ValueNode
	{
		$block = $block && self::hasItems($value);
		$text = new Encoder($block, $unit, doubleQuotedMultiline: $doubleQuoted)->encode($value);
		$text = $block
			? self::indent(rtrim($text, "\n"), $indent . $unit, first: true)
			: self::indent($text, $indent, first: false);
		$node = self::parse(str_replace("\n", $eol, $text));
		if (!$block) {
			$node->getFirstToken()->writeLeadingTrivia([]);
		}
		$node->getLastToken()->writeTrailingTrivia([]);
		return $node;
	}


	/** An item of a block, `key: value` or `- value`, with its indentation and line ending. */
	public static function buildBlockItem(
		?string $key,
		mixed $value,
		string $indent,
		string $unit,
		string $eol,
		bool $doubleQuoted = false,
	): ArrayItemNode
	{
		$head = $key === null ? '-' : self::formatKey($key) . ':';
		if ($value === null && $key !== null) {
			$text = $head;
		} elseif (self::hasItems($value)) {
			$body = rtrim(new Encoder(true, $unit, doubleQuotedMultiline: $doubleQuoted)->encode($value), "\n");
			$text = $head . "\n" . self::indent($body, $unit, first: true);
		} else {
			$text = $head . ' ' . new Encoder(false, $unit, doubleQuotedMultiline: $doubleQuoted)->encode($value);
		}
		$block = self::parse(str_replace("\n", $eol, self::indent($text, $indent, first: true)) . $eol);
		if (!$block instanceof BlockArrayNode) {
			throw new \LogicException('A block expected.');
		}
		$item = $block->items[0];
		$item->writeParent(null);
		$block->writeSlot('items', []);
		return $item;
	}


	/** The value of a document made of the text, detached, its tokens without an origin. */
	public static function parse(string $text): ValueNode
	{
		$document = new Parser()->parse($text, origin: false);
		$node = $document->value ?? throw new \LogicException('A value expected.');
		$node->writeParent(null);
		$document->writeSlot('value', null);
		return $node;
	}


	/**
	 * The text of a key: an integer key as digits, anything else as a string.
	 * @throws Exception  for a key with a line break
	 */
	public static function formatKey(string $key): string
	{
		if (str_contains($key, "\n")) {
			throw new Exception('A key cannot contain a line break.');
		}
		return Scalars::isIntegerKey($key) ? $key : Neon::encode($key);
	}


	/**
	 * The value with every iterator in it read into an array, so that it can be read more than once.
	 * @throws Exception  for an iterator whose keys cannot be the keys of an array
	 */
	public static function materialize(mixed $value, int $depth = 0): mixed
	{
		if ($depth > Parser::MaxDepth) {
			return $value; // the encoder reports the depth
		} elseif ($value instanceof \Traversable) {
			$res = [];
			foreach ($value as $k => $v) {
				if (!is_int($k) && !is_string($k)) {
					throw new Exception('Cannot encode: a key of an iterator must be int or string, ' . get_debug_type($k) . ' given');
				} elseif (array_key_exists($k, $res)) {
					throw new Exception("Cannot encode: the iterator gives the key '$k' twice");
				}
				$res[$k] = self::materialize($v, $depth + 1);
			}
			return $res;
		} elseif (is_array($value)) {
			return array_map(fn($v) => self::materialize($v, $depth + 1), $value);
		} elseif ($value instanceof Entity) {
			return new Entity(self::materialize($value->value, $depth + 1), (array) self::materialize($value->attributes, $depth + 1));
		}
		return $value;
	}


	/** Whether the value is a non-empty array or object, written as a block in a block. */
	private static function hasItems(mixed $value): bool
	{
		return is_array($value)
			? (bool) $value
			: is_object($value) && !$value instanceof Entity && !$value instanceof \DateTimeInterface && !$value instanceof \Closure && get_object_vars($value);
	}


	/** Prefixes non-empty lines; the first one only if asked. */
	private static function indent(string $text, string $prefix, bool $first): string
	{
		$lines = explode("\n", $text);
		foreach ($lines as $i => &$line) {
			if ($line !== '' && ($i > 0 || $first)) {
				$line = $prefix . $line;
			}
		}
		return implode("\n", $lines);
	}
}
