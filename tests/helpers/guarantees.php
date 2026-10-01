<?php declare(strict_types=1);

/**
 * The guarantees of editing that hold for any tree.
 */

namespace Tests;

use Nette\Neon\{Neon, Node, Token, Trivia};
use Nette\Neon\Nodes\DocumentNode;
use Tester\Assert;


final class Guarantees
{
	/** Parents agree with a walk, no node or token stands twice, trivia are canonical, the printed tree parses to the same tree. */
	public static function checkTree(DocumentNode $document): void
	{
		$seen = [];
		$walk = function (Node $node) use (&$walk, &$seen): void {
			foreach ($node->getChildren() as $child) {
				Assert::same($node, $child->parent, 'parents agree with the walk');
				Assert::false(isset($seen[spl_object_id($child)]), 'no node or token stands twice');
				$seen[spl_object_id($child)] = true;
				if ($child instanceof Node) {
					$walk($child);
				}
			}
		};
		$walk($document);
		self::checkCanonical($document);
		Assert::same(Dumper::dump($document), Dumper::dump(Neon::parse($document->print())), 'the printed tree parses to the same tree');
	}


	/** The trivia of the document are in the canonical form, checked by the rules, not by a reparse. */
	public static function checkCanonical(DocumentNode $document): void
	{
		$tokens = $document->getTokens();
		foreach ($tokens as $i => $token) {
			$prev = $tokens[$i - 1] ?? null;
			$lineEnds = array_filter($token->trailingTrivia, fn(Trivia $t) => $t->kind === Trivia::LineEnding);
			$last = $token->trailingTrivia[count($token->trailingTrivia) - 1] ?? null;
			Assert::true(
				count($lineEnds) <= 1 && (!$lineEnds || $last?->kind === Trivia::LineEnding),
				'one line ending, last of the trailing trivia',
			);
			if ($prev && !array_any($prev->trailingTrivia, fn(Trivia $t) => $t->kind === Trivia::LineEnding)) {
				Assert::same([], $token->leadingTrivia, 'no leading trivia on the line of the token before');
			}
			foreach (['leading' => $token->leadingTrivia, 'trailing' => $token->trailingTrivia] as $side => $list) {
				Assert::true(array_is_list($list), 'trivia are a list');
				Assert::same(count($list), count(array_unique(array_map(spl_object_id(...), $list))), 'an instance stands in a list once');
				foreach ($list as $k => $t) {
					$next = $list[$k + 1] ?? null;
					Assert::false(
						$t->kind === Trivia::Whitespace && $next?->kind === Trivia::Whitespace,
						'no two whitespace trivia side by side',
					);
					Assert::false(
						str_ends_with($t->text, "\r") && str_starts_with($next->text ?? '', "\n"),
						'no carriage return before a line feed of the next trivia',
					);
					if ($t->kind === Trivia::ByteOrderMark) {
						Assert::true($i === 0 && $side === 'leading' && $k === 0, 'a byte order mark only at the start');
					} elseif ($t->kind === Trivia::Comment && $next?->kind !== Trivia::LineEnding) {
						Assert::true(
							$token->kind === Token::EndOfFile || ($tokens[$i + 1] ?? null)?->kind === Token::EndOfFile,
							'a comment ends its line, but at the end of the input',
						);
					}
				}
			}
		}
		Assert::same([], $document->endOfFile->trailingTrivia);
	}


	/**
	 * The state of everything reachable from the nodes: identities, parents, slots, flags, texts and trivia. The
	 * closure checks that it is still the same.
	 * @return \Closure(): void
	 */
	public static function snapshot(Node ...$nodes): \Closure
	{
		$tokens = array_merge(...array_map(fn(Node $node) => $node->getTokens(), $nodes));
		$state = array_map(
			fn(Token $token) => [$token, $token->parent, $token->text, $token->leadingTrivia, $token->trailingTrivia],
			$tokens,
		);
		$nodeState = [];
		$walk = function (Node $node) use (&$walk, &$nodeState): void {
			$nodeState[] = [$node, self::captureNode($node)];
			foreach ($node->getChildren() as $child) {
				if ($child instanceof Node) {
					$walk($child);
				}
			}
		};
		array_map($walk, $nodes);
		$texts = array_map(fn(Node $node) => $node->print(), $nodes);

		return function () use ($nodes, $tokens, $state, $nodeState, $texts): void {
			Assert::same($texts, array_map(fn(Node $node) => $node->print(), $nodes), 'a refused operation leaves the text');
			Assert::true(array_merge(...array_map(fn(Node $node) => $node->getTokens(), $nodes)) === $tokens, 'and the tokens');
			foreach ($state as [$token, $parent, $text, $leading, $trailing]) {
				Assert::true(
					$token->parent === $parent
					&& $token->text === $text
					&& $token->leadingTrivia === $leading
					&& $token->trailingTrivia === $trailing,
					'and every token as it was',
				);
			}
			foreach ($nodeState as [$node, $captured]) {
				Assert::true(self::captureNode($node) === $captured, 'and every node with its parent, slots and flags');
			}
		};
	}


	/** @return array<string, mixed> the parent, the slots and the flag nullable, held by identity */
	private static function captureNode(Node $node): array
	{
		$res = ['parent' => $node->parent];
		foreach ($node::Slots as $slot) {
			$res[$slot] = $node->$slot;
		}
		if (property_exists($node, 'nullable')) {
			$res['nullable'] = $node->nullable;
		}
		return $res;
	}
}
