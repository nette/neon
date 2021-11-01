<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Neon;


/**
 * Parser for Nette Object Notation.
 * @internal
 */
final class Decoder
{
	public $associativeAsObjects = false;


	/**
	 * Decodes a NEON string.
	 * @return mixed
	 */
	public function decode(string $input)
	{
		$node = $this->parseToNode($input);
		if ($this->associativeAsObjects) {
			$evaluator = function (Node $node) use (&$evaluator) {
				$value = $node->toValue($evaluator);
				return $node instanceof Node\ArrayNode && $value && array_keys($value) !== range(0, count($value) - 1)
					? (object) $value
					: $value;
			};
			return $evaluator($node);
		}
		return $node->toValue();
	}


	public function parseToNode(string $input): Node
	{
		$lexer = new Lexer;
		$parser = new Parser;
		$tokens = $lexer->tokenize($input);
		return $parser->parse($tokens);
	}
}
