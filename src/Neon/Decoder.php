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
		return new Evaluator()->evaluate($this->parseToNode($input));
	}


	/** Parses a NEON string and returns the root AST node. */
	public function parseToNode(string $input): Node
	{
		$parser = new Parser;
		return $parser->parse(new TokenStream(new Lexer()->tokenize($input)));
	}
}
