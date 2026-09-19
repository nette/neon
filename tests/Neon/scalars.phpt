<?php declare(strict_types=1);

/**
 * Test: the meaning of literals, strings and keys.
 */

use Nette\Neon\{Exception, Scalars};
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


test('literals', function () {
	Assert::same(true, Scalars::evaluateLiteral('Yes'));
	Assert::same(null, Scalars::evaluateLiteral('NULL'));
	Assert::same('on', Scalars::evaluateLiteral('on'));
	Assert::same(777, Scalars::evaluateLiteral('0777'));
	Assert::same(-1, Scalars::evaluateLiteral('-1'));
	Assert::same(1.5, Scalars::evaluateLiteral('01.5'));
	Assert::same(1.0, Scalars::evaluateLiteral('1.'));
	Assert::same(INF, Scalars::evaluateLiteral('1e400'));
	Assert::same('1_000', Scalars::evaluateLiteral('1_000'));
	Assert::same('0X10', Scalars::evaluateLiteral('0X10'));
	Assert::same(16, Scalars::evaluateLiteral('0x10'));
	Assert::same(5, Scalars::evaluateLiteral('0b101'));
	Assert::same(PHP_INT_MAX, Scalars::evaluateLiteral('9223372036854775807'));
	Assert::same(PHP_INT_MIN, Scalars::evaluateLiteral('-9223372036854775808'));
	Assert::same('9223372036854775808', Scalars::evaluateLiteral('9223372036854775808'));
	Assert::same('18446744073709551615', Scalars::evaluateLiteral('0xFFFFFFFFFFFFFFFF'));
	Assert::same('340282366920938463463374607431768211455', Scalars::evaluateLiteral('0x' . str_repeat('f', 32)));
});


test('dates, and literals shaped as dates that make no date', function () {
	Assert::equal(new DateTimeImmutable('2016-06-03T19:00:00+02:00'), Scalars::evaluateLiteral('2016-06-03 19:00:00 +0200'));
	Assert::equal(new DateTimeImmutable('2016-06-03T19:00:00.5Z'), Scalars::evaluateLiteral('2016-06-03T19:00:00.5Z'));
	Assert::equal(new DateTimeImmutable('2016-06-03T19:00:00'), Scalars::evaluateLiteral('2016-06-03   19:00:00.'));
	Assert::equal(new DateTimeImmutable('2024-02-29'), Scalars::evaluateLiteral('2024-02-29'));
	foreach ([
		'2024-02-30', '2023-02-29', '2024-13-01', '2024-00-10', '2024-01-32', '2024-01-01 24:00:00', '2024-01-01 23:60:00',
		'2024-01-01 10:00:00 +15:00', '2024-01-01 10:00:00 +01:60',
	] as $text) {
		Assert::same($text, Scalars::evaluateLiteral($text), $text);
	}
});


test('keys', function () {
	Assert::same('16', Scalars::literalKey('0x10'));
	Assert::same('5.3', Scalars::literalKey('5.3'));
	Assert::same('1.0E+25', Scalars::literalKey('1e25'));
	Assert::same('0.1', Scalars::literalKey('0.1'));
	Assert::same('-0', Scalars::literalKey('-0.0'));
	Assert::same('0.33333333333333', Scalars::literalKey('0.333333333333333333'));
	Assert::same('1.0E-5', Scalars::literalKey('0.00001'));
	Assert::same('true', Scalars::literalKey('true'));
	Assert::true(Scalars::isIntegerKey('9223372036854775807'));
	Assert::false(Scalars::isIntegerKey('9223372036854775808'));
	Assert::false(Scalars::isIntegerKey('01'));

	$precision = ini_get('precision');
	ini_set('precision', '17');
	Assert::same('0.1', Scalars::literalKey('0.1'));
	ini_set('precision', (string) $precision);
});


test('strings', function () {
	Assert::same("a'b", Scalars::evaluateString("'a''b'"));
	Assert::same("a\tb\u{A0}\u{1F600}A\u{10401}", Scalars::evaluateString('"a\tb\_😀\\u0041\\uD801\\uDC01"'));
	Assert::exception(fn() => Scalars::evaluateString('"\U0041"'), Exception::class, 'Invalid escaping sequence \U0041');
	Assert::exception(fn() => Scalars::evaluateString('"\uD801\UDC01"'), Exception::class, 'Invalid UTF-8 sequence \uD801\UDC01');
	Assert::same("line\n  indented\nlast", Scalars::evaluateString("'''\n\tline\n\t  indented\n\tlast\n\t'''"));
	Assert::same('', Scalars::evaluateString("'''\n'''"));
	Assert::exception(fn() => Scalars::evaluateString('"\x"'), Exception::class, 'Invalid escaping sequence \x');
	Assert::exception(fn() => Scalars::evaluateString('"\uD800"'), Exception::class, 'Invalid UTF-8 sequence \uD800');
});
