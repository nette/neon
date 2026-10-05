<?php declare(strict_types=1);

/**
 * A projection of your own: a subclass of Evaluator expands %parameters% in strings and resolves PHP constants
 * in values, reports an unknown parameter at its position, and the tree stays as it was.
 *
 * Usage: php examples/evaluator/evaluator.php
 */

use Nette\Neon\Evaluator;
use Nette\Neon\Neon;
use Nette\Neon\Node;
use Nette\Neon\Nodes\LiteralNode;
use Nette\Neon\Nodes\StringNode;
use Nette\Neon\Place;

require __DIR__ . '/../bootstrap.php';


final class ConfigEvaluator extends Evaluator
{
	public function __construct(
		private readonly array $parameters,
	) {
	}


	protected function evaluateLiteral(LiteralNode $node, Place $place): mixed
	{
		$text = $node->token->text;
		return $place === Place::Value && preg_match('~^[A-Z][A-Z0-9_]*$~D', $text) && defined($text)
			? constant($text)
			: $this->expand(parent::evaluateLiteral($node, $place), $node);
	}


	protected function evaluateString(StringNode $node, Place $place): mixed
	{
		return $this->expand(parent::evaluateString($node, $place), $node);
	}


	private function expand(mixed $value, Node $node): mixed
	{
		return is_string($value)
			? preg_replace_callback('~%(\w+)%~', fn($m) => $this->parameters[$m[1]] ?? throw new RuntimeException("Unknown parameter %$m[1]% {$node->getPosition()}"), $value)
			: $value;
	}
}


$text = sample('
	parameters:
		tempDir: %appDir%/temp
	errorLevel: E_ALL
	"%appDir%/log": enabled   # a key is expanded too
	plain: E_NOTHING
');
$document = Neon::parse($text);
$evaluator = new ConfigEvaluator(['appDir' => '/var/www']);
var_export($evaluator->evaluate($document->value));
echo "\n", var_export($document->print() === $text, true), "\n";

try {
	$evaluator->evaluate(Neon::parse("a: 1\nb: %logDir%/app.log")->value);
} catch (RuntimeException $e) {
	echo $e->getMessage(), "\n";
}
