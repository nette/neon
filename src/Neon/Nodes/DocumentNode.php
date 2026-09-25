<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Neon\Nodes;

use Nette\Neon\Evaluator;
use Nette\Neon\Exception;
use Nette\Neon\Node;
use Nette\Neon\Token;


/**
 * The root of the tree: a value, a block or nothing, and the end of the file with the trivia after the value.
 */
final class DocumentNode extends Node
{
	public const Slots = ['value', 'endOfFile'];


	/** @internal */
	public function __construct(
		public protected(set) ?ValueNode $value,
		public protected(set) Token $endOfFile,
	) {
	}


	public function getChildren(): array
	{
		return $this->value ? [$this->value, $this->endOfFile] : [$this->endOfFile];
	}


	public function getFirstToken(): Token
	{
		return $this->value?->getFirstToken() ?? $this->endOfFile;
	}


	public function getLastToken(): Token
	{
		return $this->endOfFile;
	}


	/**
	 * The PHP value of the document, as Neon::decode() gives it.
	 * @throws Exception  for a tree whose value is not valid
	 */
	public function toValue(): mixed
	{
		return new Evaluator()->evaluate($this->value);
	}
}
