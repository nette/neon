<?php declare(strict_types=1);


/**
 * Tokens and trivia: every character of the file is in a token or in the trivia around it, and a line ends
 * in the trailing trivia of the last token on it.
 *
 * Usage: php examples/tree/tokens.php
 */
use Nette\Neon\Neon;
use Nette\Neon\Token;
use Nette\Neon\Trivia;

require __DIR__ . '/../bootstrap.php';

$show = fn(array $trivia) => implode(', ', array_map(fn(Trivia $t) => Trivia::Names[$t->kind] . ' ' . json_encode($t->text), $trivia));
foreach (Neon::parse("# header\nkey: value # note\n")->getTokens() as $token) {
	printf("%-10s %-8s leading [%s] trailing [%s]\n", Token::Names[$token->kind], json_encode($token->text), $show($token->leadingTrivia), $show($token->trailingTrivia));
}
