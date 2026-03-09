<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Neon;


/**
 * Parser for Nette Object Notation.
 * @internal
 */
final class Decoder
{
	/** Parses a NEON string and returns the corresponding PHP value. */
	public function decode(string $input): mixed
	{
		$node = $this->parseToNode($input);
		return $node->toValue();
	}


	/** Parses a NEON string and returns the root AST node. */
	public function parseToNode(string $input): Node
	{
		$lexer = new Lexer;
		$parser = new Parser;
		$tokens = $lexer->tokenize($input);
		return $parser->parse($tokens);
	}
}
