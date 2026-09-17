<?php declare(strict_types=1);

/**
 * Test: the lexer, tokens and trivia.
 */

use Nette\Neon\{Exception, Lexer, Position, Token, Trivia};
use Tester\Assert;
use Tests\Corpus;

require __DIR__ . '/../bootstrap.php';


/** @param list<Token> $tokens */
function short(array $tokens): array
{
	$name = fn(Trivia $t) => Trivia::Names[$t->kind] . ':' . json_encode($t->text);
	return array_map(fn(Token $t) => implode(' ', [
		...array_map(fn($tr) => '<' . $name($tr), $t->leadingTrivia),
		Token::Names[$t->kind] . ':' . json_encode($t->text),
		...array_map(fn($tr) => '>' . $name($tr), $t->trailingTrivia),
	]), $tokens);
}


/** @return list<string> */
function kinds(string $s): array
{
	$tokens = new Lexer()->tokenize($s);
	array_pop($tokens);
	return array_map(fn(Token $t) => Token::Names[$t->kind] . ' ' . $t->text, $tokens);
}


/** @return array{string, int, int}|null */
function error(string $s): ?array
{
	try {
		new Lexer()->tokenize($s);
		return null;
	} catch (Exception $e) {
		return [$e->getDescription(), $e->position?->line, $e->position?->column];
	}
}


test('round trip of tokens over the corpus', function () {
	foreach (['decode', 'roundtrip', 'cst'] as $category) {
		foreach (Corpus::load($category) as $case) {
			$input = (string) $case->read('.neon');
			Assert::same($input, implode('', new Lexer()->tokenize($input)), "$category/$case->name");
		}
	}
});


test('trivia attach by the convention', function () {
	Assert::same([
		'Literal:"a"',
		'Colon:":" >Whitespace:" "',
		'Literal:"1" >Whitespace:" " >Comment:"# c" >LineEnding:"\n"',
		'<LineEnding:"\n" <Comment:"# d" <LineEnding:"\n" <Whitespace:"\t" Literal:"b" >LineEnding:"\n"',
		'EndOfFile:""',
	], short(new Lexer()->tokenize("a: 1 # c\n\n# d\n\tb\n")));

	Assert::same([
		'<ByteOrderMark:"\ufeff" <Comment:"# x" <LineEnding:"\r\n" Literal:"a" >Whitespace:" " >Comment:"# y"',
		'EndOfFile:""',
	], short(new Lexer()->tokenize("\u{FEFF}# x\r\na # y")));

	Assert::same(['EndOfFile:""'], short(new Lexer()->tokenize('')));
});


test('scanner rules', function () {
	Assert::same(['Literal a:b - c -d', 'Literal :e'], kinds('a:b - c -d :e'));
	Assert::same(['Dash -', 'Literal a', 'Colon :', 'Dash -'], kinds('- a: -'));
	Assert::same(["String 'a'", 'Dash -', 'Literal b'], kinds("'a'-b"));
	Assert::same(['Literal a'], kinds('a #b'));
	Assert::same(['OpenBracket [', 'Literal a#b', 'CloseBracket ]'], kinds('[a#b]'));
	Assert::same(["String '''abc'"], kinds("'''abc'"));
	Assert::same(["String ''''"], kinds("''''"));
	Assert::same(["String '''\n  a\n  '''", 'Literal x'], kinds("'''\n  a\n  '''x"));
	Assert::same(["Literal a\rb"], kinds("a\rb"));
	Assert::same(['Literal a', 'Colon :', 'Literal b'], kinds("a:\r b"));
	Assert::same(['String "a\"b"', 'String "\\\\"'], kinds('"a\"b" "\\\\"'));
	Assert::same(['String """' . "\n" . '"""', 'Literal x'], kinds("\"\"\"\n\"\"\"x"));
	Assert::same(["String '''\n'''\n'''"], kinds("'''\n'''\n'''"));
});


test('origin positions in code points', function () {
	Assert::equal([
		new Position,
		new Position(1, 2, 1),
		new Position(1, 4, 3),
		new Position(2, 3, 8),
		new Position(2, 4, 9),
	], array_map(fn(Token $t) => $t->origin, new Lexer()->tokenize("ž: 🐴\r\n  b")));
	Assert::null(new Lexer()->tokenize('a', origin: false)[0]->origin);
});


test('errors of the scanner', function () {
	Assert::same(["Unexpected '`y`'", 1, 4], error('x: `y`'));
	Assert::same(["Unexpected ''abc<new line>def''", 1, 1], error("'abc\ndef'"));
	Assert::same(["Unexpected '\"a\\<new line>b\"'", 1, 1], error("\"a\\\nb\""));
	Assert::same(["Unexpected ''''<new line>  abc<new line>'", 1, 1], error("'''\n  abc\r\n"));
	Assert::same(["Unexpected '`" . str_repeat('ž', 39) . "'", 1, 1], error('`' . str_repeat('ž', 50)));
	Assert::same(['Invalid UTF-8 sequence.', 1, 4], error("a: \xED\xA0\x80"));
	Assert::same(['Invalid UTF-8 sequence.', 2, 2], error("ž\nž\xFF"));
	Assert::same(
		'Unexpected \'`y`\' on line 1 at column 4',
		Assert::exception(fn() => new Lexer()->tokenize('x: `y`'), Exception::class)->getMessage(),
	);
});


test('a string of megabytes', function () {
	$long = "'''\n" . str_repeat("\ta line of the string\n", 100_000) . "'''";
	Assert::same([Token::String], array_map(fn(Token $t) => $t->kind, array_slice(new Lexer()->tokenize($long), 0, -1)));
	Assert::exception(fn() => new Lexer()->tokenize("'" . str_repeat('a', 2_000_000)), Exception::class, "Unexpected ''aaaa%a%");
});


test('a failure of PCRE is not a syntax error', function () {
	$limit = ini_get('pcre.backtrack_limit');
	$jit = ini_get('pcre.jit');
	ini_set('pcre.jit', '0');
	ini_set('pcre.backtrack_limit', '10');
	try {
		Assert::exception(
			fn() => new Lexer()->tokenize("'''\n" . str_repeat("a\n", 1000) . "'''"),
			RuntimeException::class,
			'The lexer failed: %a%',
		);
	} finally {
		ini_set('pcre.backtrack_limit', (string) $limit);
		ini_set('pcre.jit', (string) $jit);
	}
});


test('Position', function () {
	Assert::same('on line 2 at column 3', (string) new Position(2, 3, 9));
	Assert::equal(new Position(2, 3, 5), new Position()->advance("ž\r\nžž"));
	Assert::exception(fn() => new Position(0), InvalidArgumentException::class);
});
