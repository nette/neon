<?php

/**
 * Test: Nette\Neon\Neon::decode simple values.
 */

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
		['null', NULL],
		['true', TRUE],
		['false', FALSE],

		// strings
		["''", ''],
		['""', ''],
		['"foo"', 'foo'],
		['"f\\no"', "f\no"],
		['"\\b\\f\\n\\r\\t\\"\\/\\\\"', "\x08\f\n\r\t\"/\\"],
		['"\u0040"', '@'],
		['"\u011B"', "\xC4\x9B"],
		['"\uD801\uDC01"', "\xf0\x90\x90\x81"], // U+10401 encoded as surrogate pair
	],

	// JSON parser implementation in PHP (json_decode); extends RFC JSON
	'PHP JSON' => [
		// extended numbers syntax (only on top level)
		['0777', 777],
		['00000777', 777],
		['0xff', 0xff],
		['.1', 0.1],
		['-.1', -0.1],

		// extended numbers syntax (everywhere)
		['[1.]', [1.0]],
		['[1.e1]', [10.0]],
		['[-1.]', [-1.0]],
		['[-1.e-1]', [-0.1]],

		// empty input
		['', NULL],
		['  ', NULL],
	],

	// Nette Object Notation; extends PHP JSON
	'NEON' => [
		// extended numbers syntax (everywhere)
		['[0777]', [777]],
		['[00000777]', [777]],
		['[0xff]', [0xff]],
		['[.1]', [0.1]],
		['[-.1]', [-0.1]],

		// more literals
		['Null', NULL],
		['NULL', NULL],
		['True', TRUE],
		['TRUE', TRUE],
		['yes', TRUE],
		['Yes', TRUE],
		['YES', TRUE],
		['on', TRUE],
		['On', TRUE],
		['ON', TRUE],
		['False', FALSE],
		['FALSE', FALSE],
		['no', FALSE],
		['No', FALSE],
		['NO', FALSE],
		['off', FALSE],
		['Off', FALSE],
		['OFF', FALSE],

		// extended string syntax
		['"\\x42 hex escape"', "\x42 hex escape"],
		["'single \\n quote'", 'single \\n quote'],

		// strings without quotes
		['a', 'a'],
		['abc', 'abc'],
		["\nabc\n", 'abc'],
		['  abc  ', 'abc'],
		[':abc', ':abc'],

		['the"string#literal', 'the"string#literal'],
		['the"string #literal', 'the"string'],
		['<literal> <literal>', '<literal> <literal>'],

		['true ""', 'true ""'],
		['false ""', 'false ""'],
		['null ""', 'null ""'],

		['@x', '@x'],
		['@true', '@true'],

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
		['{on: 42}', ['on' => 42]],
		['{no: 42}', ['no' => 42]],
		['{off: 42}', ['off' => 42]],
		['{42: 42}', [42 => 42]],
		['{0: 42}', [0 => 42]],
		['{-1: 42}', [-1 => 42]],

		// edge
		['"the\'string #literal"', "the'string #literal"],
		["'the\"string #literal'", 'the"string #literal'],
		['"the\\"string #literal"', 'the"string #literal'],
		['a                                     ', 'a'], // backtrack limit

		// BOM
		["\xEF\xBB\xBFa", 'a'],
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
	],

	// RFC JSON with valid syntax which can not be encoded in UTF-8
	'invalid encoding' => [
		['"XXX\uD801YYY\uDC01ZZZ"', 'XXXYYYZZZ'], // lead and tail surrogates alone
		['"XXX\uD801\uD801YYY"', 'XXXYYY'], // two lead surrogates
		['"XXX\uDC01\uDC01YYY"', 'XXXYYY'], // two tail surrogates
	],

	// inputs which are not valid UTF-8, but silently ignored
	'ignored invalid encoding' => [
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

foreach ($dataSet['ignored invalid encoding'] as $set) {
	Assert::same(substr($set[0], 1, -1), Neon::decode($set[0]));
}

foreach (array_merge($dataSet['invalid syntax'], $dataSet['invalid encoding']) as $set) {
	Assert::exception(function () use ($set) {
		Neon::decode($set[0]);
	}, 'Nette\Neon\Exception');
}
