<?php

/**
 * Test: Nette\Neon\Neon::decode simple values.
 */

use Nette\Neon\Neon,
	Tester\Assert;


require __DIR__ . '/../bootstrap.php';


$dataSet = array(
	// https://tools.ietf.org/html/rfc7159
	'RFC JSON' => array(
		// numbers
		array("0", 0),
		array("1", 1),
		array("0.1", 0.1),
		array("1.1", 1.1),
		array("1.100000", 1.1),
		array("1.111111", 1.111111),
		array("-0", -0),
		array("-1", -1),
		array("-0.1", -0.1),
		array("-1.1", -1.1),
		array("-1.100000", -1.1),
		array("-1.111111", -1.111111),
		array("1.1e1", 11.0),
		array("1.1e+1", 11.0),
		array("1.1e-1", 0.11),
		array("1.1E1", 11.0),
		array("1.1E+1", 11.0),
		array("1.1E-1", 0.11),

		// literals
		array("null", NULL),
		array("true", TRUE),
		array("false", FALSE),

		// strings
		array("''", ''),
		array('""', ''),
		array('"foo"', "foo"),
		array('"f\\no"', "f\no"),
		array('"\\b\\f\\n\\r\\t\\"\\/\\\\"', "\x08\f\n\r\t\"/\\"),
		array('"\u0040"', "@"),
		array('"\u011B"', "\xC4\x9B"),
		array('"\uD801\uDC01"', "\xf0\x90\x90\x81"), // U+10401 encoded as surrogate pair
	),

	// JSON parser implementation in PHP (json_decode); extends RFC JSON
	'PHP JSON' => array(
		// extended numbers syntax (only on top level)
		array('0777', 777),
		array('00000777', 777),
		array('0xff', 0xff),
		array('.1', 0.1),
		array('-.1', -0.1),

		// extended numbers syntax (everywhere)
		array('[1.]', array(1.0)),
		array('[1.e1]', array(10.0)),
		array('[-1.]', array(-1.0)),
		array('[-1.e-1]', array(-0.1)),

		// empty input
		array('', NULL),
		array('  ', NULL),
	),

	// Nette Object Notation; extends PHP JSON
	'NEON' => array(
		// extended numbers syntax (everywhere)
		array('[0777]', array(777)),
		array('[00000777]', array(777)),
		array('[0xff]', array(0xff)),
		array('[.1]', array(0.1)),
		array('[-.1]', array(-0.1)),

		// more literals
		array('Null', NULL),
		array('NULL', NULL),
		array('True', TRUE),
		array('TRUE', TRUE),
		array('yes', TRUE),
		array('Yes', TRUE),
		array('YES', TRUE),
		array('on', TRUE),
		array('On', TRUE),
		array('ON', TRUE),
		array('False', FALSE),
		array('FALSE', FALSE),
		array('no', FALSE),
		array('No', FALSE),
		array('NO', FALSE),
		array('off', FALSE),
		array('Off', FALSE),
		array('OFF', FALSE),

		// extended string syntax
		array('"\\x42 hex escape"', "\x42 hex escape"),
		array("'single \\n quote'", "single \\n quote"),

		// strings without quotes
		array('a', 'a'),
		array('abc', 'abc'),
		array("\nabc\n", 'abc'),
		array('  abc  ', 'abc'),
		array(':abc', ':abc'),

		array('the"string#literal', 'the"string#literal'),
		array('the"string #literal', 'the"string'),
		array('<literal> <literal>', '<literal> <literal>'),

		array('true ""', 'true ""'),
		array('false ""', 'false ""'),
		array('null ""', 'null ""'),

		array('@x', '@x'),
		array('@true', '@true'),

		array('42 px', '42 px'),
		array('42 .2', '42 .2'),
		array('42 2', '42 2'),
		array('42 e1', '42 e1'),
		array('--1', '--1'),
		array('-1e', '-1e'),
		array('1e+-1', '1e+-1'),

		// object keys without quotes
		array("{true: 42}", array('true' => 42)),
		array("{false: 42}", array('false' => 42)),
		array("{null: 42}", array('null' => 42)),
		array("{yes: 42}", array('yes' => 42)),
		array("{on: 42}", array('on' => 42)),
		array("{no: 42}", array('no' => 42)),
		array("{off: 42}", array('off' => 42)),
		array("{42: 42}", array(42 => 42)),
		array("{0: 42}", array(0 => 42)),
		array("{-1: 42}", array(-1 => 42)),

		// edge
		array('"the\'string #literal"', "the'string #literal"),
		array("'the\"string #literal'", 'the"string #literal'),
		array('"the\\"string #literal"', 'the"string #literal'),
		array('a                                     ', 'a'), // backtrack limit

		// BOM
		array("\xEF\xBB\xBFa", 'a'),
	),

	// inputs with invalid syntax, but still valid UTF-8
	'invalid syntax' => array(
		array('"\\a invalid escape"'),
		array('"\\v invalid escape"'),
		array('"\\u202 invalid escape"'),
		array('"\\012 invalid escape"'),
		array('"\\\' invalid escape"'),

		array('"Unterminated string'),
		array('"Unterminated string\\"'),
		array('"Unterminated string\\\\\\"'),

		array('"42" ""'),
		array('"" ""'),
		array('[] ""'),
		array('[true] ""'),
		array('{} ""'),
		array('{"x":true} ""'),
		array('"Garbage""After string"'),
		array('function () { return 0; }'),
		array("[1, 2"),
		array('{"x": 3'),
		array('1e--1]'),
	),

	// RFC JSON with valid syntax which can not be encoded in UTF-8
	'invalid encoding' => array(
		array('"XXX\uD801YYY\uDC01ZZZ"', 'XXXYYYZZZ'), // lead and tail surrogates alone
		array('"XXX\uD801\uD801YYY"', 'XXXYYY'), // two lead surrogates
		array('"XXX\uDC01\uDC01YYY"', 'XXXYYY'), // two tail surrogates
	),

	// inputs which are not valid UTF-8, but silently ignored
	'ignored invalid encoding' => array(
		array("'\xc3\x28'"), // Invalid 2 Octet Sequence
		array("'\xa0\xa1'"), // Invalid Sequence Identifier
		array("'\xe2\x28\xa1'"), // Invalid 3 Octet Sequence (in 2nd Octet)
		array("'\xe2\x82\x28'"), // Invalid 3 Octet Sequence (in 3rd Octet)
		array("'\xf0\x28\x8c\xbc'"), // Invalid 4 Octet Sequence (in 2nd Octet)
		array("'\xf0\x90\x28\xbc'"), // Invalid 4 Octet Sequence (in 3rd Octet)
		array("'\xf0\x28\x8c\x28'"), // Invalid 4 Octet Sequence (in 4th Octet)
		array("'\xf8\xa1\xa1\xa1\xa1'"), // Valid 5 Octet Sequence (but not Unicode!)
		array("'\xfc\xa1\xa1\xa1\xa1\xa1'"), // Valid 6 Octet Sequence (but not Unicode!)
		array("'\xed\xa0\x80'"), // invalid code point (U+D800)
		array("'\xf0\x82\x82\xac'"), // overlong encoding of U+20AC (euro sign)
	),
);


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
