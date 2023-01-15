<?php

/**
 * Test: Nette\Neon\Neon::decode.
 */

declare(strict_types=1);

use Nette\Neon\Neon;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


$dataSet = [
	// https://tools.ietf.org/html/rfc7159
	'RFC JSON' => [
		// numbers
		['0', 0],
		['1', 1],
		['0.1', 0.1],
		['1.1', 1.1],
		['1.100000', 1.1],
		['1.111111', 1.111111],
		['-0', -0],
		['-1', -1],
		['-0.1', -0.1],
		['-1.1', -1.1],
		['-1.100000', -1.1],
		['-1.111111', -1.111111],
		['1.1e1', 11.0],
		['1.1e+1', 11.0],
		['1.1e-1', 0.11],
		['1.1E1', 11.0],
		['1.1E+1', 11.0],
		['1.1E-1', 0.11],

		// literals
		['null', null],
		['true', true],
		['false', false],

		// strings
		["''", ''],
		["'foo'", 'foo'],
		["'fo''o'", "fo'o"],
		['""', ''],
		['"foo"', 'foo'],
		['"f\\no"', "f\no"],
		['"\\b\\f\\n\\r\\t\\"\\/\\\\"', "\x08\f\n\r\t\"/\\"],
		['"\u0040"', '@'],
		['"\u011B"', "\u{11B}"],
		['"\uD801\uDC01"', "\u{10401}"], // U+10401 encoded as surrogate pair
	],

	// JSON parser implementation in PHP (json_decode); extends RFC JSON
	'PHP JSON' => [
		// extended numbers syntax (only on top level)
		['0777', 777],
		['00000777', 777],
		['0xff', 0xff],
		//['0b00010111', '0b00010111'],
		['.1', 0.1],
		['-.1', -0.1],

		// extended numbers syntax (everywhere)
		['[1.]', [1.0]],
		['[1.e1]', [10.0]],
		['[-1.]', [-1.0]],
		['[-1.e-1]', [-0.1]],

		// empty input
		['', null],
		['  ', null],
	],

	// Nette Object Notation; extends PHP JSON
	'NEON' => [
		// extended numbers syntax (everywhere)
		['[0777]', [777]],
		['[00000777]', [777]],
		['[0xff]', [0xff]],
		['0b00010111', 23],
		['0o777', 511],
		['[.1]', [0.1]],
		['[-.1]', [-0.1]],

		// more literals
		['Null', null],
		['NULL', null],
		['True', true],
		['TRUE', true],
		['yes', true],
		['Yes', true],
		['YES', true],
		['False', false],
		['FALSE', false],
		['no', false],
		['No', false],
		['NO', false],

		// extended string syntax
		["'single \\n quote'", 'single \\n quote'],

		// strings without quotes
		['a', 'a'],
		['abc', 'abc'],
		["\nabc\n", 'abc'],
		['  abc  ', 'abc'],
		[':abc', ':abc'],
		['a:bc', 'a:bc'],
		['-abc', '-abc'],
		['a-bc', 'a-bc'],
		['abc-', 'abc-'],

		['the"string#literal', 'the"string#literal'],
		['the"string #literal', 'the"string'],
		['<literal> <literal>', '<literal> <literal>'],

		['true ""', 'true ""'],
		['false ""', 'false ""'],
		['null ""', 'null ""'],

		['@x', '@x'],
		['@true', '@true'],

		['!hello', '!hello'],

		['::', '::'],
		[':0', ':0'],
		[':-1', ':-1'],
		[':true', ':true'],
		[':false', ':false'],
		[':null', ':null'],
		[':NULL', ':NULL'],
		['-:', '-:'],
		['-0', -0],
		['-true', '-true'],

		['42 px', '42 px'],
		['42 .2', '42 .2'],
		['42 2', '42 2'],
		['42 e1', '42 e1'],
		['--1', '--1'],
		['-1e', '-1e'],
		['1e+-1', '1e+-1'],

		// object keys without quotes
		['{true: 42}', ['true' => 42]],
		['{false: 42}', ['false' => 42]],
		['{null: 42}', ['null' => 42]],
		['{yes: 42}', ['yes' => 42]],
		['{no: 42}', ['no' => 42]],
		['{42: 42}', [42 => 42]],
		['{0: 42}', [0 => 42]],
		['{-1: 42}', [-1 => 42]],

		// key value separator
		['{a: b}', ['a' => 'b']],
		['{a:b}', ['a:b']],
		['{:a : b}', [':a' => 'b']],
		['{a= b}', ['a' => 'b']],
		['{a=b}', ['a' => 'b']],
		['{a =b}', ['a' => 'b']],

		// comments
		['#abc', null],
		['a: #abc', ['a' => null]],
		['a:#abc', 'a:#abc'],
		['abc#', 'abc#'],

		// edge
		['"the\'string #literal"', "the'string #literal"],
		["'the\"string #literal'", 'the"string #literal'],
		['"the\\"string #literal"', 'the"string #literal'],
		['a                                     ', 'a'], // backtrack limit
	],

	// deprecated NEON syntax
	'deprecated syntax' => [
	],

	// inputs with invalid syntax, but still valid UTF-8
	'invalid syntax' => [
		['"\\a invalid escape"'],
		['"\\v invalid escape"'],
		['"\\u202 invalid escape"'],
		['"\\012 invalid escape"'],
		['"\\\' invalid escape"'],

		['"Unterminated string'],
		['"Unterminated string\\"'],
		['"Unterminated string\\\\\\"'],

		['"42" ""'],
		['"" ""'],
		['[] ""'],
		['[true] ""'],
		['{} ""'],
		['{"x":true} ""'],
		['"Garbage""After string"'],
		['function () { return 0; }'],
		['[1, 2'],
		['{"x": 3'],
		['1e--1]'],
		['=abc'],
		['{a :b}'],
		['a :b'],

		['-['],
		['-{'],
		['-('],
		[':['],
		[':{'],
		[':('],
	],

	// RFC JSON with valid syntax which can not be encoded in UTF-8
	'invalid encoding' => [
		['"XXX\uD801YYY\uDC01ZZZ"'], // lead and tail surrogates alone
		['"XXX\uD801\uD801YYY"'], // two lead surrogates
		['"XXX\uDC01\uDC01YYY"'], // two tail surrogates
		["'\xc3\x28'"], // Invalid 2 Octet Sequence
		["'\xa0\xa1'"], // Invalid Sequence Identifier
		["'\xe2\x28\xa1'"], // Invalid 3 Octet Sequence (in 2nd Octet)
		["'\xe2\x82\x28'"], // Invalid 3 Octet Sequence (in 3rd Octet)
		["'\xf0\x28\x8c\xbc'"], // Invalid 4 Octet Sequence (in 2nd Octet)
		["'\xf0\x90\x28\xbc'"], // Invalid 4 Octet Sequence (in 3rd Octet)
		["'\xf0\x28\x8c\x28'"], // Invalid 4 Octet Sequence (in 4th Octet)
		["'\xf8\xa1\xa1\xa1\xa1'"], // Valid 5 Octet Sequence (but not Unicode!)
		["'\xfc\xa1\xa1\xa1\xa1\xa1'"], // Valid 6 Octet Sequence (but not Unicode!)
		["'\xed\xa0\x80'"], // invalid code point (U+D800)
		["'\xf0\x82\x82\xac'"], // overlong encoding of U+20AC (euro sign)
	],
];


foreach (array_merge($dataSet['RFC JSON'], $dataSet['PHP JSON'], $dataSet['NEON']) as $set) {
	echo "$set[0]\n";
	Assert::same($set[1], Neon::decode($set[0]));
}

foreach ($dataSet['deprecated syntax'] as $set) {
	echo "$set[0]\n";
	Assert::same($set[1], @Neon::decode($set[0])); // @ is deprecated
}

foreach (array_merge($dataSet['invalid syntax'], $dataSet['invalid encoding']) as $set) {
	echo "$set[0]\n";
	Assert::exception(
		fn() => Neon::decode($set[0]),
		Nette\Neon\Exception::class,
	);
}

// datetime
Assert::equal(
	new DateTimeImmutable('2016-06-03T19:00:00+02:00'),
	Neon::decode('2016-06-03 19:00:00 +0200'),
);
Assert::equal(
	new DateTimeImmutable('2016-06-03T19:00:00+02:00'),
	Neon::decode('2016-06-03 19:00:00 +02:00'),
);
Assert::equal(
	new DateTimeImmutable('2016-06-03T19:00:00'),
	Neon::decode('2016-06-03 19:00:00'),
);
Assert::equal(
	new DateTimeImmutable('2016-06-03T00:00:00'),
	Neon::decode('2016-06-03'),
);
