<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Neon;

use Nette\Neon\Nodes\ArrayItemNode;
use Nette\Neon\Nodes\ArrayNode;
use Nette\Neon\Nodes\BlockArrayNode;
use Nette\Neon\Nodes\DocumentNode;
use Nette\Neon\Nodes\EntityChainNode;
use Nette\Neon\Nodes\EntityNode;
use Nette\Neon\Nodes\InlineArrayNode;
use Nette\Neon\Nodes\LiteralNode;
use Nette\Neon\Nodes\StringNode;
use Nette\Neon\Nodes\ValueNode;
use function count, is_array, is_bool, is_float, is_int, is_object, is_string, strlen;
use const JSON_THROW_ON_ERROR, JSON_UNESCAPED_SLASHES, JSON_UNESCAPED_UNICODE;


/**
 * The editing operations of the tree. The nodes expose them as methods, each run in Journal::atomic(), so that
 * a refused edit changes nothing.
 * @internal
 * @phpstan-type PathSegment  string|int|array{item: int}
 * @phpstan-type KeysBefore  array{keys: array<int, string>, list: bool}
 */
final class Editor
{
	private const BooleanWords = [['true', 'false'], ['yes', 'no']];


	/** Writes the value of an item; an equal value is a no-op, a changed one keeps the style where it can. */
	public static function setItemValue(ArrayItemNode $item, mixed $value): void
	{
		if ($value instanceof ValueNode) {
			self::setItemNode($item, $value);
			return;
		}
		$old = $item->value;
		$value = Builder::materialize($value);
		if ($old ? self::isSameValue($value, $old->toValue()) : $value === null) {
			return;
		}
		$node = $value === null ? null : self::styleScalar($old, $value) ?? Builder::buildValue($value, ...self::getPlace($item, $old, $value));
		if ($old && $node && self::isSameNode($node, $old)) { // an object equal to the array
			return;
		} elseif ($node === null && !$item->colon && !$item->bullet) {
			$node = self::createLiteral('null'); // an inline item without a key cannot lose its value
		}
		self::replaceValue($item, $node);
		self::guardDashSubblocks($item->parent);
	}


	/** Puts the node into the value slot or empties it, without the comparison and styling of setItemValue(). */
	public static function setItemNode(ArrayItemNode $item, ?ValueNode $node): void
	{
		self::replaceValue($item, $node);
		self::guardDashSubblocks($item->parent);
	}


	/**
	 * A dash right after a key at the same indentation reads as a dash subblock of that key. So a key without
	 * a value followed by a dash gets an explicit null, and a dash right after a dash subblock gets its key
	 * written out, which ends the subblock.
	 */
	private static function guardDashSubblocks(?Node $block): void
	{
		if (!$block instanceof BlockArrayNode) {
			return;
		}
		self::guardEnd($block);
		foreach ($block->items as $i => $item) {
			$next = $block->items[$i + 1] ?? null;
			if (!$item->key || !$next?->bullet) {
				continue;
			} elseif ($item->value === null) {
				self::replaceValue($item, self::createLiteral('null'));
			} elseif (
				$item->value instanceof BlockArrayNode
				&& Layout::getIndentation($item->value->items[0]) === Layout::getIndentation($item)
			) {
				self::giveKey($next, $block->getItemKeys()[$i + 1]);
			}
		}
	}


	/** Spaces on the last line of the input would be read as the indentation of a missing value at the end. */
	private static function guardEnd(Node $at): void
	{
		$root = Layout::getRoot($at);
		if (!$root) {
			return;
		}
		$end = $root->endOfFile;
		$item = self::getTokenBefore($end)?->parent;
		if ($item instanceof ArrayItemNode && $item->value === null && self::isIndentation($end->leadingTrivia)) {
			self::lead($end, self::stripIndentation($end->leadingTrivia));
		}
	}


	/**
	 * Where a value of the item is built; a replaced block keeps the indentation it had.
	 * @return array{block: bool, indent: string, unit: string, eol: string, doubleQuoted: bool}
	 */
	private static function getPlace(ArrayItemNode $item, ?ValueNode $old, mixed $value): array
	{
		$indent = Layout::getIndentation($item);
		$blockIndent = $old instanceof BlockArrayNode ? Layout::getIndentation($old->items[0]) : null;
		$unit = $blockIndent !== null && str_starts_with($blockIndent, $indent) && strlen($blockIndent) > strlen($indent)
			? substr($blockIndent, strlen($indent))
			: Layout::getUnit($item);
		return [
			'block' => $item->parent instanceof BlockArrayNode,
			'indent' => $indent,
			'unit' => $unit,
			'eol' => Layout::getLineEnding($item),
			'doubleQuoted' => self::hasFragileString($item, $value),
		];
	}


	/** Puts a node into the value slot, moving the trivia so that the lines stay as they were. */
	private static function replaceValue(ArrayItemNode $item, ?ValueNode $node): void
	{
		$old = $item->value;
		$head = $item->colon ?? $item->bullet;
		if ($node === $old) {
			return;
		} elseif ($node instanceof BlockArrayNode && ($item->parent instanceof InlineArrayNode || !$head)) {
			throw new Exception('A block cannot stand in an inline array.');
		} elseif ($node) {
			self::checkPlaceable($item, $node);
		} elseif (!$head) {
			throw new Exception('The value of an item without a key cannot be empty.');
		}

		if (!$head) { // an item of an inline array without a key: the node takes the place of the old one
			assert($old !== null);
			self::takeEdges($node, $old);
			self::setSlots($item, ['value' => $node]);
			return;
		}

		$headEnds = self::hasLineEnding($head->trailingTrivia);
		$oldOnOwnLine = $old !== null && $headEnds;
		$finalLineEnding = $old ? self::hasLineEnding($old->getLastToken()->trailingTrivia) : $headEnds;

		if ($node === null) {
			assert($old !== null);
			if (!$oldOnOwnLine) {
				self::trail($head, $old->getLastToken()->trailingTrivia);
			} elseif (!$finalLineEnding) {
				self::trail($head, self::stripLineEnding($head->trailingTrivia));
			}
			self::setSlots($item, ['value' => null]);
			$end = self::getTokenAfter($head);
			if ($end?->kind === Token::EndOfFile) { // spaces on a last line would be read as the indentation of the missing value
				self::lead($end, self::stripIndentation($end->leadingTrivia));
			}
			return;
		}

		$eol = Layout::getLineEnding($head);
		if ($node instanceof BlockArrayNode) {
			$place = self::getPlace($item, $old, null);
			self::reindentBlock($node, $place['indent'] . $place['unit']);
			if ($old && !$oldOnOwnLine) {
				self::trail($head, $old->getLastToken()->trailingTrivia);
			}
			if (!self::hasLineEnding($head->trailingTrivia)) {
				self::trail($head, [...self::stripTrailingSpace($head->trailingTrivia), new Trivia(Trivia::LineEnding, $eol)]);
			}
			if ($old instanceof BlockArrayNode && $oldOnOwnLine) { // the lines above the old block stay
				$first = $node->getFirstToken();
				self::lead($first, self::joinTrivia(self::stripIndentation($old->getFirstToken()->leadingTrivia), $first->leadingTrivia));
			}
			self::trail($node->getLastToken(), $finalLineEnding ? [new Trivia(Trivia::LineEnding, $eol)] : []);
			self::setSlots($item, ['value' => $node]);
			return;
		}

		if ($old && !$old instanceof BlockArrayNode) { // the token takes the place of the old one, on its line
			self::takeEdges($node, $old);
		} else {
			$trailing = $old && !$oldOnOwnLine // a block opened on the line of a dash ends the line itself
				? $old->getLastToken()->trailingTrivia
				: $head->trailingTrivia; // the line ending, and a comment, of the head move after the value
			self::lead($node->getFirstToken(), []);
			self::trail($node->getLastToken(), $finalLineEnding ? $trailing : self::stripLineEnding($trailing));
			self::trail($head, [new Trivia(Trivia::Whitespace, ' ')]);
		}
		self::setSlots($item, ['value' => $node]);
	}


	/** A node that comes into a slot of the owner must be free, must not hold the owner and must not nest too deep. */
	private static function checkPlaceable(Node $owner, Node $node): void
	{
		self::checkMovable($owner, $node);
		if ($node instanceof EntityNode && !$node->attributes) {
			throw new \InvalidArgumentException('An entity without parentheses can only be a link of a chain.');
		}
		self::checkDepth($owner, $node);
	}


	private static function checkMovable(Node $owner, Node $node): void
	{
		if ($node->parent) {
			throw new \InvalidArgumentException('The node already has a parent, detach() or clone it first.');
		}
		for ($ancestor = $owner; $ancestor; $ancestor = $ancestor->parent) {
			if ($ancestor === $node) {
				throw new \InvalidArgumentException('A node cannot be put into its own subtree.');
			}
		}
	}


	/** The arrays around the owner and in the node must not nest deeper than the parser reads. */
	private static function checkDepth(Node $owner, ?Node $node): void
	{
		$around = 0;
		for ($ancestor = $owner; $ancestor; $ancestor = $ancestor->parent) {
			$around += $ancestor instanceof ArrayNode ? 1 : 0;
		}
		$depth = function (Node $n) use (&$depth): int {
			$max = 0;
			foreach ($n->getChildren() as $child) {
				if ($child instanceof ArrayItemNode || $child instanceof ValueNode) {
					$max = max($max, $depth($child));
				}
			}
			return ($n instanceof ArrayNode ? 1 : 0) + $max;
		};
		if ($node && $around + $depth($node) > Parser::MaxDepth) {
			throw new Exception('Too deep nesting');
		}
	}


	/** Shifts the indentation of all lines of a block, so that its items stand at the given one. */
	private static function reindentBlock(BlockArrayNode $block, string $to): void
	{
		$from = ($block->items[1] ?? $block->items[0])->getIndentation(); // the first item may stand on the line of a dash
		self::reindent($block, $from, $to);
		$first = $block->getFirstToken();
		self::lead($first, [...self::stripIndentation($first->leadingTrivia), ...self::createIndentation($to)]);
	}


	/** The new node takes the leading trivia of the first token and the trailing trivia of the last one. */
	private static function takeEdges(Node $node, Node $old): void
	{
		self::lead($node->getFirstToken(), $old->getFirstToken()->leadingTrivia);
		self::trail($node->getLastToken(), $old->getLastToken()->trailingTrivia);
	}


	/** Equality of values as PHP sees them: floats by value, dates by instant and offset, arrays in their order. */
	private static function isSameValue(mixed $a, mixed $b): bool
	{
		return match (true) {
			is_float($a) && is_float($b) => $a === $b || (is_nan($a) && is_nan($b)),
			$a instanceof \DateTimeInterface && $b instanceof \DateTimeInterface => $a->format('Y-m-d H:i:s.u P') === $b->format('Y-m-d H:i:s.u P'),
			$a instanceof Entity && $b instanceof Entity => self::isSameValue($a->value, $b->value) && self::isSameValue($a->attributes, $b->attributes),
			is_array($a) && is_array($b) => array_keys($a) === array_keys($b) && array_all($a, fn($v, $k) => self::isSameValue($v, $b[$k])),
			default => $a === $b,
		};
	}


	/** Whether the new node has the value of the old one; an empty map is not an empty list. */
	private static function isSameNode(ValueNode $node, ValueNode $old): bool
	{
		return self::isSameValue($node->toValue(), $old->toValue()) && self::isEmptyMap($node) === self::isEmptyMap($old);
	}


	private static function isEmptyMap(ValueNode $node): bool
	{
		return $node instanceof InlineArrayNode && !$node->items && $node->openBracket->kind === Token::OpenBrace;
	}


	/** A scalar written in the style of the old one: base of an integer, word of a boolean, quotes of a string. */
	private static function styleScalar(?ValueNode $old, mixed $value): ?ValueNode
	{
		if ($old instanceof LiteralNode) {
			$text = $old->token->text;
			if (preg_match('~^0([xob])([0-9a-fA-F]+)$~D', $text, $m) && is_int($value) && $value >= 0) {
				$digits = match ($m[1]) {
					'x' => dechex($value),
					'o' => decoct($value),
					default => decbin($value),
				};
				$digits = str_pad($digits, strlen($m[2]), '0', STR_PAD_LEFT);
				return self::createLiteral('0' . $m[1] . (preg_match('~[A-F]~', $m[2]) ? strtoupper($digits) : $digits));

			} elseif (is_bool($value)) {
				foreach (self::BooleanWords as $words) {
					if (in_array(strtolower($text), $words, true)) {
						$res = $words[$value ? 0 : 1];
						return self::createLiteral(match (true) {
							$text === strtoupper($text) => strtoupper($res),
							(bool) preg_match('~^[A-Z]~', $text) => ucfirst($res),
							default => $res,
						});
					}
				}

			} elseif (is_string($value) && !str_contains($value, "\n") && is_string($old->toValue())) {
				$res = Neon::encode($value);
				return $res === $value ? self::createLiteral($res) : null;
			}

		} elseif ($old instanceof StringNode && is_string($value)) {
			$quote = $old->token->text[0];
			$multiline = preg_match('~^(\'\'\'|""")\r?\n~', $old->token->text);
			if (!$multiline && !str_contains($value, "\n") && preg_match('//u', $value)) {
				if ($quote === "'" && !preg_match('~[\x00-\x08\x0B-\x1F]~', $value)) {
					return self::createString("'" . str_replace("'", "''", $value) . "'");
				} elseif ($quote === '"') {
					return self::createString(json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
				}
			}
		}
		return null;
	}


	private static function createLiteral(string $text): LiteralNode
	{
		return new LiteralNode(Token::fromText($text));
	}


	private static function createString(string $text): StringNode
	{
		return new StringNode(Token::fromText($text));
	}


	/**
	 * Adds an item to an array, at the end or before the item at the position.
	 * @throws Exception  for a key that is there already, or a value that cannot stand in the array
	 */
	public static function addItem(ArrayNode $array, ?string $key, mixed $value, ?int $position = null): ArrayItemNode
	{
		$items = $array->items;
		$at = $position ?? count($items);
		if ($at < 0 || $at > count($items)) {
			throw new \OutOfRangeException("Position $at is out of the array.");
		} elseif ($key !== null && $array->findItem($key)) {
			throw new Exception("Duplicated key '$key'");
		} elseif ($value instanceof ValueNode) {
			if ($value instanceof BlockArrayNode && $array instanceof InlineArrayNode) {
				throw new Exception('A block cannot stand in an inline array.');
			}
			self::checkPlaceable($array, $value);
		} else {
			$value = Builder::materialize($value);
		}

		$before = self::getKeysBefore($array);
		$item = match (true) {
			$array instanceof BlockArrayNode => self::addToBlock($array, $key, $value, $at),
			$array instanceof InlineArrayNode => self::addToInline($array, $key, $value, $at),
			default => throw new \LogicException('Unknown array ' . $array::class),
		};
		self::keepKeys($array, $before, $key === null);
		self::guardDashSubblocks($array);
		return $item;
	}


	/**
	 * The keys of the items before an edit, and whether they made a list.
	 * @return KeysBefore
	 */
	private static function getKeysBefore(ArrayNode $array): array
	{
		$keys = [];
		$itemKeys = $array->getItemKeys();
		foreach ($itemKeys as $i => $key) {
			$keys[spl_object_id($array->items[$i])] = $key;
		}
		return ['keys' => $keys, 'list' => self::getListLength($itemKeys) !== null];
	}


	/**
	 * The length of the list the keys of the items make in the decoded array, where an overwritten key keeps the place
	 * of its first item; null when they make no list.
	 * @param  list<string>  $itemKeys
	 */
	private static function getListLength(array $itemKeys): ?int
	{
		$seen = [];
		foreach ($itemKeys as $key) {
			if (!isset($seen[$key])) {
				if ($key !== (string) count($seen)) {
					return null;
				}
				$seen[$key] = true;
			}
		}
		return count($seen);
	}


	/**
	 * An edit must not change the keys of the other items. In a list, an item added or removed without a key
	 * renumbers the following ones, which is what a list means; anywhere else, an item whose implicit key
	 * would change gets its old key written out (`{5: a, b}` without `5: a` is `{6: b}`).
	 * @param  KeysBefore  $before
	 */
	private static function keepKeys(ArrayNode $array, array $before, bool $keyless): void
	{
		if ($before['list'] && $keyless) {
			$array->getItemKeys(); // an implicit key out of range fails here
			return;
		}
		do {
			$changed = false;
			$keys = $array->getItemKeys();
			foreach ($array->items as $i => $item) {
				$old = $before['keys'][spl_object_id($item)] ?? null;
				if ($old !== null && !$item->key && $keys[$i] !== $old) {
					self::giveKey($item, $old);
					$changed = true;
					break; // the key changes the implicit keys after it
				}
			}
		} while ($changed);
	}


	/** Writes the key out: `- b` becomes `6: b`, an inline `b` becomes `6: b`. */
	private static function giveKey(ArrayItemNode $item, string $key): void
	{
		[, $keyNode, $colon] = self::createHead($key);
		assert($keyNode && $colon);
		if ($item->bullet) {
			self::lead($keyNode->getFirstToken(), $item->bullet->leadingTrivia);
			if ($item->value instanceof BlockArrayNode && !self::hasLineEnding($item->bullet->trailingTrivia)) {
				// a block opened on the line of the dash moves to the lines below the key
				$first = $item->value->items[0];
				self::lead($first->getFirstToken(), self::createIndentation(Layout::getIndentation($first)));
				self::trail($colon, [new Trivia(Trivia::LineEnding, Layout::getLineEnding($item))]);
			} else {
				self::trail($colon, $item->bullet->trailingTrivia);
			}
			self::setSlots($item, ['bullet' => null, 'key' => $keyNode, 'colon' => $colon]);
		} else {
			$first = $item->getFirstToken();
			self::lead($keyNode->getFirstToken(), $first->leadingTrivia);
			self::lead($first, []);
			self::setSlots($item, ['key' => $keyNode, 'colon' => $colon]);
		}
	}


	private static function addToBlock(BlockArrayNode $block, ?string $key, mixed $value, int $at): ArrayItemNode
	{
		$items = $block->items;
		$sibling = $items[min($at, count($items) - 1)];
		$indent = Layout::getIndentation($sibling);
		$eol = Layout::getLineEnding($sibling);
		$unit = Layout::getUnit($block);
		if ($value instanceof ValueNode) { // the head first, then the node comes in as the value of a placed item
			$item = Builder::buildBlockItem($key, $key === null ? 0 : null, $indent, $unit, $eol);
			self::placeInBlock($block, $item, $at, $indent, $eol);
			self::replaceValue($item, $value);
			return $item;
		}
		$item = Builder::buildBlockItem($key, $value, $indent, $unit, $eol, self::hasFragileString($block, $value));
		self::checkDepth($block, $item->value);
		self::placeInBlock($block, $item, $at, $indent, $eol);
		return $item;
	}


	/** Puts a finished item into a block; its first token holds its comments and its indentation. */
	private static function placeInBlock(BlockArrayNode $block, ArrayItemNode $item, int $at, string $indent, string $eol): void
	{
		$items = $block->items;
		$sibling = $items[min($at, count($items) - 1)];
		if ($at === count($items)) {
			$last = $sibling->getLastToken();
			if (!self::hasLineEnding($last->trailingTrivia)) { // a missing final line ending stays missing
				self::trail($last, [...$last->trailingTrivia, new Trivia(Trivia::LineEnding, $eol)]);
				self::trail($item->getLastToken(), []);
			}
		} elseif (!self::isAtLineStart($sibling)) { // the first item of a block opened on the line of a dash
			self::lead($item->getFirstToken(), []);
			self::lead($sibling->getFirstToken(), self::createIndentation($indent));
		} else {
			[$preamble, $owned] = self::splitLeading($sibling->getFirstToken()->leadingTrivia);
			self::lead($item->getFirstToken(), [...$preamble, ...$item->getFirstToken()->leadingTrivia]);
			self::lead($sibling->getFirstToken(), $owned);
		}
		self::setList($block, 'items', [...array_slice($items, 0, $at), $item, ...array_slice($items, $at)]);
	}


	/** Replaces the indentation prefix of every line of the node, its comment lines included. */
	private static function reindent(Node $node, string $from, string $to): void
	{
		$tokens = $node->getTokens();
		foreach ($tokens as $k => $token) {
			$atLineStart = $k === 0 || self::hasLineEnding($tokens[$k - 1]->trailingTrivia);
			$trivia = [];
			foreach ($token->leadingTrivia as $t) {
				if ($atLineStart && $t->kind === Trivia::Whitespace && str_starts_with($t->text, $from)) {
					array_push($trivia, ...self::createIndentation($to . substr($t->text, strlen($from))));
				} else {
					if ($atLineStart && $from === '' && $t->kind !== Trivia::LineEnding && $t->kind !== Trivia::ByteOrderMark) {
						array_push($trivia, ...self::createIndentation($to)); // a line without indentation gets the new one
					}
					$trivia[] = $t;
				}
				$atLineStart = $t->kind === Trivia::LineEnding;
			}
			if ($atLineStart && $from === '') { // the line of the token itself
				array_push($trivia, ...self::createIndentation($to));
			}
			self::lead($token, self::joinAll($trivia));
		}
	}


	/** @return array{?Token, StringNode|LiteralNode|null, ?Token} */
	private static function createHead(?string $key): array
	{
		if ($key === null) {
			return [self::addSpace(Token::fromText('-')), null, null];
		}
		$text = Builder::formatKey($key);
		return [
			null,
			$text[0] === "'" || $text[0] === '"' ? self::createString($text) : self::createLiteral($text),
			self::addSpace(Token::fromText(':')),
		];
	}


	private static function addSpace(Token $token): Token
	{
		return self::trail($token, [new Trivia(Trivia::Whitespace, ' ')]);
	}


	private static function addToInline(InlineArrayNode $array, ?string $key, mixed $value, int $at): ArrayItemNode
	{
		$items = $array->items;
		$spread = self::isSpread($array);
		$eol = Layout::getLineEnding($array);
		$indentation = self::getItemIndentation($array);
		if ($value instanceof ArrayItemNode) { // a finished item, inserted
			$item = $value;
		} else {
			if ($value instanceof ValueNode) {
				$node = $value;
				self::lead($node->getFirstToken(), []);
				self::trail($node->getLastToken(), []);
			} else {
				$node = Builder::buildValue($value, false, $indentation, Layout::getUnit($array), $eol, self::hasFragileString($array, $value));
			}
			[, $keyNode, $colon] = $key === null ? [null, null, null] : self::createHead($key);
			self::checkDepth($array, $node);
			$item = new ArrayItemNode(null, $keyNode, $colon, $node);
		}
		$space = fn() => $spread ? [new Trivia(Trivia::LineEnding, $eol)] : [new Trivia(Trivia::Whitespace, ' ')];

		if (!$items) {
			// nothing to separate
		} elseif ($at === count($items)) {
			$last = $items[count($items) - 1];
			if ($last->separator) { // a trailing comma: the new item gets one too
				$comma = self::trail(Token::fromText(','), $last->separator->trailingTrivia);
				self::trail($last->separator, $space());
				self::setSlots($item, ['separator' => $comma]);
			} else {
				$lastToken = $last->getLastToken();
				$lineEnd = $spread && self::hasLineEnding($lastToken->trailingTrivia); // else the bracket closes the line of the last item
				self::trail($item->getLastToken(), $lineEnd ? [new Trivia(Trivia::LineEnding, $eol)] : $lastToken->trailingTrivia);
				$comma = self::trail(Token::fromText(','), match (true) {
					$lineEnd => $lastToken->trailingTrivia,
					$spread => [new Trivia(Trivia::LineEnding, $eol)],
					default => [new Trivia(Trivia::Whitespace, ' ')],
				});
				self::trail($lastToken, []);
				self::setSlots($last, ['separator' => $comma]);
			}
			if ($spread) {
				self::lead($item->getFirstToken(), self::createIndentation($indentation));
			}
		} else {
			$next = $items[$at];
			[$preamble, $owned] = self::splitLeading($next->getFirstToken()->leadingTrivia);
			$comments = self::stripIndentation($owned);
			$nextIndentation = self::isAtLineStart($next) ? self::getIndentationOf($owned) : $indentation;
			self::lead($item->getFirstToken(), [...$preamble, ...self::createIndentation(self::getIndentationOf($owned))]);
			self::lead($next->getFirstToken(), $spread ? [...$comments, ...self::createIndentation($nextIndentation)] : $comments);
			self::setSlots($item, ['separator' => self::trail(Token::fromText(','), $space())]);
		}

		self::setList($array, 'items', [...array_slice($items, 0, $at), $item, ...array_slice($items, $at)]);
		return $item;
	}


	/** The indentation of the first item of an inline array that begins its line, or none. */
	private static function getItemIndentation(InlineArrayNode $array): string
	{
		foreach ($array->items as $item) {
			if (self::isAtLineStart($item)) {
				return $item->getIndentation();
			}
		}
		return '';
	}


	/** Whether the items of an inline array stand on lines of their own. */
	private static function isSpread(InlineArrayNode $array): bool
	{
		return self::hasLineEnding($array->openBracket->trailingTrivia)
			|| array_any($array->items, fn(ArrayItemNode $item) => $item->separator && self::hasLineEnding($item->separator->trailingTrivia));
	}


	/**
	 * Replaces the key of an item, in the quotes of the old one when it can be written in them; or by a key node.
	 * @throws Exception  for an item without a key or a key that is there already
	 */
	public static function setItemKey(ArrayItemNode $item, string|StringNode|LiteralNode $key): void
	{
		$array = $item->parent instanceof ArrayNode ? $item->parent : null;
		$old = $item->key;
		if (!$old) {
			throw new Exception('The item has no key.');
		} elseif (!is_string($key)) {
			if (str_contains($key->token->text, "\n")) {
				throw new \InvalidArgumentException('A key is a StringNode or a LiteralNode on one line.');
			}
			self::checkPlaceable($item, $key);
		} elseif ($item->getKeyValue() === $key) {
			return;
		}
		$value = match (true) {
			is_string($key) => $key,
			$key instanceof StringNode => Scalars::evaluateString($key->token->text),
			default => Scalars::literalKey($key->token->text),
		};
		$found = $array?->findItem($value);
		if ($found && $found !== $item) {
			throw new Exception("Duplicated key '$value'");
		}

		$before = $array ? self::getKeysBefore($array) : null;
		if (is_string($key)) {
			$node = self::styleScalar($old, $key);
			if (!$node instanceof StringNode && !$node instanceof LiteralNode) {
				$text = Builder::formatKey($key);
				$node = $text[0] === "'" || $text[0] === '"' ? self::createString($text) : self::createLiteral($text);
			}
		} else {
			$node = $key;
		}
		self::takeEdges($node, $old);
		self::setSlots($item, ['key' => $node]);
		if ($node instanceof LiteralNode && $item->colon?->kind === Token::Colon && $item->value && !$item->colon->trailingTrivia) {
			self::trail($item->colon, [new Trivia(Trivia::Whitespace, ' ')]); // `"a":b` stays two tokens, `a:b` would be one literal
		}
		if ($array && $before) {
			$before['keys'][spl_object_id($item)] = $value;
			self::keepKeys($array, $before, false);
			self::guardDashSubblocks($array);
		}
	}


	/**
	 * Puts the node into the slot the old one stands in; the slot decides what that means.
	 * @throws Exception  for a node that cannot stand in the slot
	 */
	public static function replaceNode(ValueNode $old, ValueNode $node): void
	{
		$parent = $old->parent;
		if ($node === $old) {
			return;
		} elseif ($parent instanceof ArrayItemNode && $parent->value === $old) {
			self::setItemNode($parent, $node);
		} elseif ($parent instanceof ArrayItemNode) {
			if (!$node instanceof StringNode && !$node instanceof LiteralNode) {
				throw new \InvalidArgumentException('A key is a StringNode or a LiteralNode on one line.');
			}
			self::setItemKey($parent, $node);
		} elseif ($parent instanceof DocumentNode) {
			self::setDocumentNode($parent, $node);
		} elseif ($parent instanceof EntityNode && $parent->value === $old) {
			if (!$node instanceof StringNode && !$node instanceof LiteralNode && !$node instanceof InlineArrayNode) {
				throw new \InvalidArgumentException('The head of an entity is a StringNode, a LiteralNode or an InlineArrayNode.');
			}
			self::checkEntityHead($node);
			if ($parent->parent instanceof EntityChainNode && $parent->parent->entities[0] !== $parent && !$node instanceof LiteralNode) {
				throw new \InvalidArgumentException('A link of a chain after the first has a literal head.');
			}
			self::checkPlaceable($parent, $node);
			self::takeEdges($node, $old);
			self::setSlots($parent, ['value' => $node]);
		} elseif ($parent instanceof EntityNode) {
			if (!$node instanceof InlineArrayNode || $node->openBracket->kind !== Token::OpenParen) {
				throw new \InvalidArgumentException('The attributes of an entity are in parentheses.');
			}
			self::checkPlaceable($parent, $node);
			self::takeEdges($node, $old);
			self::setSlots($parent, ['attributes' => $node]);
		} elseif ($parent instanceof EntityChainNode) {
			if (!$node instanceof EntityNode) {
				throw new \InvalidArgumentException('A link of a chain is an EntityNode.');
			}
			$entities = array_map(fn($entity) => $entity === $old ? $node : $entity, $parent->entities);
			if ($entities[0] !== $node && !$node->value instanceof LiteralNode) {
				throw new \InvalidArgumentException('A link of a chain after the first has a literal head.');
			} elseif (!$node->attributes && $old instanceof EntityNode && $old->attributes) {
				throw new \InvalidArgumentException('The link had parentheses, its replacement needs them too.');
			}
			self::checkMovable($parent, $node);
			self::checkDepth($parent, $node);
			self::takeEdges($node, $old);
			self::setList($parent, 'entities', $entities);
		} else {
			throw new \LogicException('The node is not in a tree.');
		}
	}


	/** The head of an entity is not the reserved name of a chain, which only a chain itself is. */
	private static function checkEntityHead(ValueNode $node): void
	{
		$name = match (true) {
			$node instanceof StringNode => Scalars::evaluateString($node->token->text),
			$node instanceof LiteralNode => $node->token->text,
			default => null,
		};
		if ($name === Neon::Chain) {
			throw new \InvalidArgumentException("Entity name '" . Neon::Chain . "' is reserved.");
		}
	}


	/** Takes the node out of its slot with its edge trivia; only a value of an item with a head and the root can be empty. */
	public static function detachNode(ValueNode $node): void
	{
		$parent = $node->parent;
		if ($parent instanceof ArrayItemNode && $parent->value === $node && ($parent->colon || $parent->bullet)) {
			self::setItemNode($parent, null);
		} elseif ($parent instanceof DocumentNode) { // the preamble and the byte order mark stay in the document
			$first = $node->getFirstToken();
			[$preamble, $owned] = self::splitLeading($first->leadingTrivia);
			self::lead($first, $owned);
			self::setSlots($parent, ['value' => null]);
			self::lead($parent->endOfFile, self::joinTrivia($preamble, $parent->endOfFile->leadingTrivia));
		} elseif ($parent) {
			throw new \LogicException('The slot of the node cannot be empty; use replaceWith().');
		}
	}


	/**
	 * Puts the node into the place of the root: a block gets the indentation of the old root block, or none.
	 * @throws Exception  for a node that cannot stand there
	 */
	public static function setDocumentNode(DocumentNode $document, ?ValueNode $node): void
	{
		$old = $document->value;
		if ($node === $old) {
			return;
		} elseif (!$node) {
			self::emptyRoot($document);
			return;
		}
		self::checkPlaceable($document, $node);

		$end = $document->endOfFile;
		if ($node instanceof BlockArrayNode) {
			self::reindentBlock($node, $old instanceof BlockArrayNode ? $old->items[0]->getIndentation() : '');
		}
		$first = $node->getFirstToken();
		$blockLeading = $node instanceof BlockArrayNode ? $first->leadingTrivia : [];
		if ($old) {
			$leading = $old->getFirstToken()->leadingTrivia;
			self::lead($first, $node instanceof BlockArrayNode ? self::joinTrivia(self::stripIndentation($leading), $blockLeading) : $leading);
			self::trail($node->getLastToken(), $old->getLastToken()->trailingTrivia);
		} else { // the first value of an empty document comes after what the document holds
			$leading = self::stripIndentation($end->leadingTrivia);
			$eol = Layout::getLineEnding($end);
			$lineEnding = ($leading[count($leading) - 1] ?? null)?->kind === Trivia::Comment ? [new Trivia(Trivia::LineEnding, $eol)] : [];
			self::lead($first, self::joinTrivia([...$leading, ...$lineEnding], $blockLeading));
			self::trail($node->getLastToken(), self::hasLineEnding($end->leadingTrivia) ? [new Trivia(Trivia::LineEnding, $eol)] : []);
			self::lead($end, []);
		}
		self::setSlots($document, ['value' => $node]);
	}


	/** Empties the document; what stood above the value and the comment on its line stay. */
	private static function emptyRoot(DocumentNode $document): void
	{
		$old = $document->value;
		if (!$old) {
			return;
		}
		$end = $document->endOfFile;
		$trailing = $old->getLastToken()->trailingTrivia;
		$comment = array_values(array_filter($trailing, fn(Trivia $t) => $t->kind === Trivia::Comment));
		$kept = [
			...self::stripIndentation($old->getFirstToken()->leadingTrivia),
			...$comment,
			...($comment && (self::hasLineEnding($trailing) || $end->leadingTrivia) ? [new Trivia(Trivia::LineEnding, Layout::getLineEnding($old))] : []),
		];
		self::setSlots($document, ['value' => null]);
		self::lead($end, self::joinTrivia($kept, $end->leadingTrivia));
	}


	/**
	 * The item at the path, or an error naming the first key that is not there.
	 * @param  list<PathSegment>  $path
	 * @param  ?list<PathSegment>  $fullPath
	 * @throws Exception  for a path that is not there
	 */
	public static function resolvePath(DocumentNode $document, array $path, ?array $fullPath = null): ArrayItemNode
	{
		if (!$path) {
			throw new \InvalidArgumentException('The path is empty.');
		}
		self::checkPath($path);
		$container = $document->value;
		$item = null;
		foreach ($path as $segment) {
			$array = self::findArray($container);
			$item = !$array ? null : (is_array($segment) ? $array->items[self::checkSegment($segment)] ?? null : $array->findItem($segment));
			if (!$item) {
				throw new Exception("No item '" . self::formatSegment($segment) . "' in the path " . self::formatPath($fullPath ?? $path));
			}
			$container = $item->value;
		}
		return $item;
	}


	/** The array of a container: the array itself, or the attributes of an entity. */
	public static function findArray(?Node $node): ?ArrayNode
	{
		return match (true) {
			$node instanceof EntityNode => $node->attributes,
			$node instanceof ArrayNode => $node,
			default => null,
		};
	}


	/**
	 * Writes the value at the path; a missing last key is added, unless create is false.
	 * @param  list<PathSegment>  $path
	 * @throws Exception  for a path that is not there or a value that cannot stand there
	 */
	public static function setByPath(DocumentNode $document, array $path, mixed $value, bool $create): void
	{
		if (!$path) {
			self::setDocumentValue($document, $value);
			return;
		}

		self::checkPath($path);
		$last = $path[count($path) - 1];
		$owner = count($path) > 1 ? self::resolvePath($document, array_slice($path, 0, -1), $path) : null;
		$container = $owner ? $owner->value : $document->value;
		$array = self::findArray($container);
		$existing = $array ? (is_array($last) ? $array->items[self::checkSegment($last)] ?? null : $array->findItem($last)) : null;
		if ($existing) {
			self::setItemValue($existing, $value);
			return;
		} elseif (!$create || is_array($last)) {
			throw new Exception("No item '" . self::formatSegment($last) . "' in the path " . self::formatPath($path) . ', and create is false');
		}

		$key = (string) $last;
		if ($array) {
			self::addItem($array, self::isImplicitKey($array, $key) ? null : $key, $value);
		} elseif ($container === null && $owner) {
			self::setItemValue($owner, [$key => $value]);
		} elseif ($container === null) {
			self::setDocumentValue($document, [$key => $value]);
		} else {
			throw new Exception('The value at ' . self::formatPath(array_slice($path, 0, -1)) . ' is not an array');
		}
	}


	/**
	 * Adds an item to the array at the path; a missing value there (`key:`, an empty document) becomes an array with
	 * the item, a `null` written out is a value and no array.
	 * @param  list<PathSegment>  $path
	 * @throws Exception  for a path that is not there or a value there that is not an array
	 */
	public static function addByPath(DocumentNode $document, array $path, ?string $key, mixed $value, ?int $position): ArrayItemNode
	{
		$owner = $path ? self::resolvePath($document, $path) : null;
		$container = $owner ? $owner->value : $document->value;
		if ($array = self::findArray($container)) {
			return self::addItem($array, $key, $value, $position);
		} elseif ($container !== null) {
			throw new Exception('The value at ' . self::formatPath($path) . ' is not an array');
		} elseif ($position !== null && $position !== 0) {
			throw new \OutOfRangeException("Position $position is out of the array.");
		}
		$placeholder = $value instanceof ValueNode ? null : $value; // a node comes into the item as into any other
		self::setByPath($document, $path, $key === null ? [$placeholder] : [$key => $placeholder], true);
		$array = self::findArray($owner ? $owner->value : $document->value);
		$item = $array->items[0] ?? throw new \LogicException('An item expected.');
		if ($value instanceof ValueNode) {
			self::setItemValue($item, $value);
		}
		return $item;
	}


	/** Whether a key is the one the array would assign to an item without a key. */
	private static function isImplicitKey(ArrayNode $array, string $key): bool
	{
		$length = self::getListLength($array->getItemKeys());
		return $length !== null && (string) $length === $key;
	}


	private static function setDocumentValue(DocumentNode $document, mixed $value): void
	{
		if ($value instanceof ValueNode) {
			self::setDocumentNode($document, $value);
			return;
		}
		$old = $document->value;
		$value = Builder::materialize($value);
		if ($old ? self::isSameValue($value, $old->toValue()) : $value === null) {
			return;
		}
		$node = $value === null
			? null
			: self::styleScalar($old, $value) ?? Builder::buildValue(
				$value,
				block: true,
				indent: '',
				unit: Layout::getUnit($document),
				eol: Layout::getLineEnding($document->endOfFile),
				doubleQuoted: self::hasFragileString($document, $value),
			);
		if ($old && $node && self::isSameNode($node, $old)) { // an object equal to the array
			return;
		}
		self::setDocumentNode($document, $node);
	}


	/** @param  array<mixed>  $path */
	private static function checkPath(array $path): void
	{
		if (!array_is_list($path)) {
			throw new \InvalidArgumentException('A path is given as a list of segments.');
		}
	}


	/** @param  array<mixed>  $segment */
	private static function checkSegment(array $segment): int
	{
		return is_int($segment['item'] ?? null) && count($segment) === 1
			? $segment['item']
			: throw new \InvalidArgumentException('A segment of a path is a key or [item => position].');
	}


	/** @param  PathSegment  $segment */
	private static function formatSegment(string|int|array $segment): string
	{
		return is_array($segment) ? '#' . self::checkSegment($segment) : (string) $segment;
	}


	/** @param  list<PathSegment>  $path */
	private static function formatPath(array $path): string
	{
		return implode('.', array_map(self::formatSegment(...), $path));
	}


	/** @param  array<string, Node|Token|null>  $slots */
	private static function setSlots(Node $node, array $slots): void
	{
		foreach ($slots as $slot => $child) {
			$old = $node->$slot;
			if ($child === $old) {
				continue;
			} elseif ($child) {
				self::checkFree($node, $child);
				$child->writeParent($node);
			}
			if ($old instanceof Node || $old instanceof Token) {
				$old->writeParent(null);
			}
			$node->writeSlot($slot, $child);
		}
	}


	/**
	 * Replaces a list of children; those that leave it lose their parent, new ones must have none.
	 * @param  list<Node>  $list
	 */
	private static function setList(Node $node, string $slot, array $list): void
	{
		$old = [];
		foreach (is_array($node->$slot) ? $node->$slot : throw new \LogicException("The slot $slot is not a list.") as $child) {
			$old[] = $child instanceof Node ? $child : throw new \LogicException("The slot $slot holds " . get_debug_type($child) . '.');
		}
		$kept = $present = [];
		foreach ($list as $child) {
			$kept[spl_object_id($child)] = true;
		}
		foreach ($old as $child) {
			$present[spl_object_id($child)] = true;
			if (!isset($kept[spl_object_id($child)])) {
				$child->writeParent(null);
			}
		}
		foreach ($list as $child) {
			if (!isset($present[spl_object_id($child)])) {
				self::checkFree($node, $child);
				$child->writeParent($node);
			}
		}
		$node->writeSlot($slot, $list);
	}


	private static function checkFree(Node $parent, Node|Token $child): void
	{
		if ($child->parent) {
			throw new \InvalidArgumentException('The node already has a parent, detach() or clone it first.');
		}
		for ($node = $parent; $node; $node = $node->parent) {
			if ($node === $child) {
				throw new \InvalidArgumentException('A node cannot be put into its own subtree.');
			}
		}
	}


	/** @param  list<Trivia>  $trivia */
	private static function lead(Token $token, array $trivia): Token
	{
		$token->writeLeadingTrivia(self::keepCarriageReturns($trivia));
		return $token;
	}


	/** @param  list<Trivia>  $trivia */
	private static function trail(Token $token, array $trivia): Token
	{
		$token->writeTrailingTrivia(self::keepCarriageReturns($trivia));
		return $token;
	}


	/**
	 * A line feed put after a lone carriage return, which ends a comment or whitespace, is written as CRLF, so the CR
	 * stays its text.
	 * @param  list<Trivia>  $trivia
	 * @return list<Trivia>
	 */
	private static function keepCarriageReturns(array $trivia): array
	{
		foreach ($trivia as $i => $t) {
			if ($t->text === "\n" && $i > 0 && str_ends_with($trivia[$i - 1]->text, "\r")) {
				$trivia[$i] = new Trivia(Trivia::LineEnding, "\r\n");
			}
		}
		return $trivia;
	}


	/**
	 * Splits the leading trivia of the first token of an item into the preamble (lines separated from the item
	 * by a blank line, and the blank lines) and what the item owns: the comment lines directly above it and its
	 * indentation.
	 * @param  list<Trivia>  $trivia
	 * @return array{list<Trivia>, list<Trivia>}
	 */
	private static function splitLeading(array $trivia): array
	{
		$lines = self::splitLines($trivia);
		$split = count($lines) - 1; // the last line is the indentation
		while ($split > 0 && array_any($lines[$split - 1], fn(Trivia $t) => $t->kind === Trivia::Comment)) {
			$split--;
		}
		$preamble = array_merge(...array_slice($lines, 0, $split));
		$owned = array_merge(...array_slice($lines, $split));
		if (($owned[0] ?? null)?->kind === Trivia::ByteOrderMark) { // the mark belongs to the start of the document
			$preamble[] = array_shift($owned);
		}
		return [$preamble, $owned];
	}


	/**
	 * Groups trivia into lines, each ending with its line ending; the last group has none.
	 * @param  list<Trivia>  $trivia
	 * @return non-empty-list<list<Trivia>>
	 */
	private static function splitLines(array $trivia): array
	{
		$lines = [[]];
		foreach ($trivia as $t) {
			$lines[count($lines) - 1][] = $t;
			if ($t->kind === Trivia::LineEnding) {
				$lines[] = [];
			}
		}
		return $lines;
	}


	/**
	 * The comment lines without the indentation of the item they stood above.
	 * @param  list<Trivia>  $owned
	 * @return list<Trivia>
	 */
	private static function stripIndentation(array $owned): array
	{
		return self::isIndentation($owned) ? array_slice($owned, 0, -1) : $owned;
	}


	/** @param  list<Trivia>  $trivia */
	private static function isIndentation(array $trivia): bool
	{
		return ($trivia[count($trivia) - 1] ?? null)?->kind === Trivia::Whitespace;
	}


	/**
	 * @param  list<Trivia>  $trivia
	 * @return list<Trivia>
	 */
	private static function stripTrailingSpace(array $trivia): array
	{
		return self::stripIndentation($trivia);
	}


	/** @param  list<Trivia>  $owned */
	private static function getIndentationOf(array $owned): string
	{
		return self::isIndentation($owned) ? $owned[count($owned) - 1]->text : '';
	}


	/** @return list<Trivia> */
	private static function createIndentation(string $indent): array
	{
		return $indent === '' ? [] : [new Trivia(Trivia::Whitespace, $indent)];
	}


	/**
	 * Concatenates two lists of trivia, merging whitespace that would meet.
	 * @param  list<Trivia>  $a
	 * @param  list<Trivia>  $b
	 * @return list<Trivia>
	 */
	private static function joinTrivia(array $a, array $b): array
	{
		return self::isIndentation($a) && ($b[0] ?? null)?->kind === Trivia::Whitespace
			? [...array_slice($a, 0, -1), ...$b]
			: [...$a, ...$b];
	}


	/**
	 * @param  list<Trivia>  $trivia
	 * @return list<Trivia>
	 */
	private static function joinAll(array $trivia): array
	{
		$res = [];
		foreach ($trivia as $t) {
			$res = self::joinTrivia($res, [$t]);
		}
		return $res;
	}


	/**
	 * Whether a multiline string of the value has to use """: the document holds an empty ''' string closed on the
	 * line right after its opening, so a later line starting with ''' would close it instead.
	 */
	private static function hasFragileString(Node $at, mixed $value): bool
	{
		if (!self::hasMultilineString($value)) {
			return false;
		}
		$root = $at;
		while ($root->parent) {
			$root = $root->parent;
		}
		return array_any($root->getTokens(), fn(Token $token) => $token->kind === Token::String && preg_match("~^'''\\r?\\n[\\t ]*'''$~D", $token->text));
	}


	/**
	 * Whether the value holds a string the encoder writes on more lines; a value nested too deep counts as one, and so
	 * does an iterator, which is read only once, by the encoder.
	 */
	private static function hasMultilineString(mixed $value, int $depth = 0): bool
	{
		if (is_string($value)) {
			return str_contains($value, "\n");
		} elseif ($depth > Parser::MaxDepth || $value instanceof \Traversable) {
			return true;
		} elseif (is_array($value) || (is_object($value) && !$value instanceof \DateTimeInterface)) {
			foreach (is_array($value) ? $value : get_object_vars($value) as $item) {
				if (self::hasMultilineString($item, $depth + 1)) {
					return true;
				}
			}
		}
		return false;
	}


	/** Whether the item begins its line; not so the first item of a block opened on the line of a dash. */
	private static function isAtLineStart(ArrayItemNode $item): bool
	{
		$before = self::getTokenBefore($item->getFirstToken());
		return !$before || self::hasLineEnding($before->trailingTrivia);
	}


	/** @param  list<Trivia>  $trivia */
	private static function hasLineEnding(array $trivia): bool
	{
		return array_any($trivia, fn(Trivia $t) => $t->kind === Trivia::LineEnding);
	}


	/**
	 * @param  list<Trivia>  $trivia
	 * @return list<Trivia>
	 */
	private static function stripLineEnding(array $trivia): array
	{
		return array_values(array_filter($trivia, fn(Trivia $t) => $t->kind !== Trivia::LineEnding));
	}


	private static function getTokenAfter(Token $token): ?Token
	{
		return $token->findNeighbour(backward: false);
	}


	private static function getTokenBefore(Token $token): ?Token
	{
		return $token->findNeighbour(backward: true);
	}
}
