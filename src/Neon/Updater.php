<?php

declare(strict_types=1);

namespace Nette\Neon;

use Nette\Neon\Node\ArrayItemNode;
use Nette\Neon\Node\ArrayNode;
use Nette\Neon\Node\BlockArrayNode;


/*
normalization must take place and this notation must be removed:

- a:
  b:

a:
- a
- b

*/

/** @internal */
class Updater
{
	private TokenStream $stream;
	private Node $node;

	/** @var string[] */
	private array $replacements;

	/** @var string[] */
	private array $appends;


	public function __construct(string $input)
	{
		$this->stream = (new Lexer)->tokenize($input);
		$this->node = (new Parser)->parse($this->stream);
	}


	public function getNodeClone(): Node
	{
		return (new Traverser)->traverse($this->node, function (Node $node) {
			$dolly = clone $node;
			$dolly->data['originalNode'] = $node;
			return $dolly;
		});
	}


	public function updateValue($newValue): string
	{
		$newNode = (new Encoder)->valueToNode($newValue);
		$this->guessOriginalNodes($this->node, $newNode);
		return $this->updateNode($newNode);
	}


	public function updateNode(Node $newNode): string
	{
		$this->replacements = $this->appends = [];
		$this->replaceNode($this->node, $newNode);
		$res = '';
		foreach ($this->stream->tokens as $i => $token) {
			$res .= $this->appends[$i] ?? '';
			$res .= $this->replacements[$i] ?? $token->text;
		}
		return $res;
	}


	public function guessOriginalNodes(Node $oldNode, Node $newNode): void
	{
		if ($oldNode instanceof BlockArrayNode && $newNode instanceof ArrayNode) {
			$newNode->data['originalNode'] = $oldNode;
			$differ = new Differ(function (ArrayItemNode $old, ArrayItemNode $new) {
				if ($old->key || $new->key) {
					return ($old->key ? $old->key->toValue() : null) === ($new->key ? $new->key->toValue() : null);
				} else {
					return $old->value->toValue() === $new->value->toValue();
				}
			});
			$steps = $differ->diff($oldNode->items, $newNode->items);
			foreach ($steps as [$type, $oldItem, $newItem]) {
				if ($type === $differ::Keep) { // keys are same
					$newItem->data['originalNode'] = $oldItem;
					// TODO: original for keys?
					$this->guessOriginalNodes($oldItem->value, $newItem->value);
				}
			}
		} elseif ($oldNode->toValue() === $newNode->toValue()) {
			$newNode->data['originalNode'] = $oldNode;
		}
	}


	private function replaceNode(Node $oldNode, Node &$newNode, string $indentation = null): void
	{
		// assumes that $oldNode->data['originalNode'] === $newNode
		if ($oldNode->toValue() === $newNode->toValue()) {
			return;

		} elseif ($oldNode instanceof ArrayNode && $newNode instanceof ArrayNode) {
			$tmp = $newNode->items;
			$newNode = clone $oldNode;
			$newNode->items = $tmp;
			if ($oldNode instanceof BlockArrayNode) {
				$this->replaceArrayItems($oldNode->items, $newNode->items, $indentation . $oldNode->indentation);
				return;
			}
		}

		$newStr = $newNode instanceof BlockArrayNode ? "\n" : '';
		$newStr .= $newNode->toString();
		$newStr = rtrim($newStr);
		$newStr = self::indent1($newStr, $indentation . "\t");

		$this->replaceWith($newStr, $this->stream->findIndex($oldNode->start), $this->stream->findIndex($oldNode->end));
	}


	/**
	 * @param  ArrayItemNode[]  $oldItems
	 * @param  ArrayItemNode[]  $newItems
	 */
	private function replaceArrayItems(array $oldItems, array $newItems, string $indentation): void
	{
		$differ = new Differ(fn(Node $old, Node $new) => $old === ($new->data['originalNode'] ?? null));
		$steps = $differ->diff($oldItems, $newItems);
		$newPos = $this->skipLeft($this->stream->findIndex($oldItems[0]->start), Token::Whitespace, $this->stream);

		foreach ($steps as [$type, $oldItem, $newItem]) {
			if ($type === $differ::Remove) {
				$startPos = $this->skipLeft($this->stream->findIndex($oldItem->start), Token::Whitespace, $this->stream);
				$endPos = $this->skipRight($this->stream->findIndex($oldItem->end), Token::Whitespace, $this->stream,
					Token::Comment
				);
				$endPos = $this->skipRight($endPos, Token::Newline, $this->stream);
				$this->replaceWith('', $startPos, $endPos);

			} elseif ($type === $differ::Keep) {
				$this->replaceNode($oldItem->value, $newItem->value, $indentation);
				$newPos = $this->skipRight($this->stream->findIndex($oldItem->value->end), Token::Whitespace, $this->stream, Token::Comment);
				$newPos = $this->skipRight($newPos, Token::Newline, $this->stream); // jen jednou!
				$newPos++;

			} elseif ($type === $differ::Add) {
				$newStr = Node\ArrayItemNode::itemsToBlockString([$newItem], $indentation);
				@$this->appends[$newPos] .= $indentation . $newStr;
			}
		}
	}


	private function replaceWith(string $new, int $start, int $end): void
	{
		for ($i = $start; $i <= $end; $i++) {
			$this->replacements[$i] ??= '';
		}
		$this->replacements[$start] .= $new;
	}


	private static function indent1(string $s, string $indentation = "\t"): string
	{
		return str_replace("\n", "\n" . $indentation, $s);
	}


	public function skipRight(int $index, ...$types): int
	{
		while (in_array($this->stream->tokens[$index + 1]->type, $types, true)) {
			$index++;
		}
		return $index;
	}


	public function skipLeft(int $index, ...$types): int
	{
		while (in_array($this->stream->tokens[$index - 1]->type ?? null, $types, true)) {
			$index--;
		}
		return $index;
	}
}
