<?php

/**
 * Test: Nette\Neon\Neon::encode.
 */

declare(strict_types=1);

use Nette\Neon\Entity;
use Nette\Neon\Neon;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


Assert::same(
	"[true, 'TRUE', 'tRuE', 'true', false, 'FALSE', 'fAlSe', 'false', null, 'NULL', 'nUlL', 'null', 'yes', 'no', 'on', 'off']",
	Neon::encode([
		true, 'TRUE', 'tRuE', 'true',
		false, 'FALSE', 'fAlSe', 'false',
		null, 'NULL', 'nUlL', 'null',
		'yes', 'no', 'on', 'off',
	]),
);

Assert::same(
	"[1, 1.0, 0, 0.0, -1, -1.2, '1', '1.0', '-1']",
	Neon::encode([1, 1.0, 0, 0.0, -1, -1.2, '1', '1.0', '-1']),
);

Assert::same(
	"['1', '0xAA', '0o12', '0b110', '+1', '-1', '.50', '1e10']",
	Neon::encode(['1', '0xAA', '0o12', '0b110', '+1', '-1', '.50', '1e10']),
);

Assert::same(
	"[\\, '''', '\"', '''\n\n\n''', '''\n\t\\ \n\n''']",
	Neon::encode(['\\', "'", '"', "\n", "\\ \n"]),
);

Assert::same(
	"'''\n\taaa\n\t'''bbb\n'''",
	Neon::encode("aaa\n'''bbb"),
);

Assert::same(
	"\"\"\"\n\taaa\n\t \t'''bbb\n\"\"\"",
	Neon::encode("aaa\n \t'''bbb"),
);

Assert::same(
	"'''\n\taaa'''\n\tbbb\n'''",
	Neon::encode("aaa'''\nbbb"),
);

Assert::same(
	"\"\"\"\n\taaa\n\t \t'''bbb\n\t \t\"\"\\\"ccc\n\"\"\"",
	Neon::encode("aaa\n \t'''bbb\n \t\"\"\"ccc"),
);

Assert::same(
	"{multi: ['''\n\tone\n\ttwo\n\tthree\\ne \"'\t\n''']}",
	Neon::encode(['multi' => ["one\ntwo\nthree\\ne \"'\t"]]),
);

Assert::same(
	"['[', ']', '{', '}', ':', ': ', '=', '#']",
	Neon::encode(['[', ']', '{', '}', ':', ': ', '=', '#']),
);

Assert::same(
	'[1, 2, 3]',
	Neon::encode([1, 2, 3]),
);

Assert::same(
	'{1: 1, 2: 2, 3: 3}',
	Neon::encode([1 => 1, 2, 3]),
);

Assert::same(
	'{foo: 1, bar: [2, 3]}',
	Neon::encode(['foo' => 1, 'bar' => [2, 3]]),
);

Assert::same(
	'item(a, b)',
	Neon::encode(Neon::decode('item(a, b)')),
);

Assert::same(
	'item<item>(a, b)',
	Neon::encode(Neon::decode('item<item>(a, b)')),
);

Assert::same(
	'item(foo: a, bar: b)',
	Neon::encode(Neon::decode('item(foo: a, bar: b)')),
);

Assert::same(
	'[]()',
	Neon::encode(Neon::decode('[]()')),
);

$entity = new Entity('ent');
$entity->attributes = [];
Assert::same(
	'ent()',
	Neon::encode($entity),
);

Assert::same(
	"',žlu/ťoučký'",
	Neon::encode(',žlu/ťoučký'),
);

Assert::same(
	"foo: 1\nbar:\n\tx:\n\t\t- 1\n\t\t- 2\n\n\ty:\n\t\t- 3\n\t\t- 4\n\nbaz: null\n",
	Neon::encode(['foo' => 1, 'bar' => ['x' => [1, 2], 'y' => [3, 4]], 'baz' => null], Neon::BLOCK),
);

Assert::same(
	"foo: 1\nbar:\n  x:\n    - 1\n    - 2\n\n  y:\n    - 3\n    - 4\n\nbaz: null\n",
	Neon::encode(['foo' => 1, 'bar' => ['x' => [1, 2], 'y' => [3, 4]], 'baz' => null], true, '  '),
);

Assert::same(
	'ent(1)inner(2, 3)',
	Neon::encode(Neon::decode('ent(1)inner(2, 3)')),
);
Assert::same(
	'foo(1, 2)::bar(3)',
	Neon::encode(Neon::decode('foo(1,2)::bar(3)')),
);

Assert::same(
	'2016-06-03 19:00:00 +0200',
	Neon::encode(new DateTime('2016-06-03T19:00:00+02:00')),
);

Assert::same(
	'2016-06-03 19:00:00 +0200',
	Neon::encode(new DateTimeImmutable('2016-06-03T19:00:00+02:00')),
);

Assert::same(
	'{foo: bar}',
	Neon::encode((object) ['foo' => 'bar']),
);

Assert::same(
	'{}',
	Neon::encode((object) []),
);

Assert::same(
	'{}',
	Neon::encode(new stdClass),
);

Assert::same(
	'[]',
	Neon::encode([], Neon::BLOCK),
);

Assert::exception(
	fn() => Neon::encode(INF),
	Nette\Neon\Exception::class,
	'INF and NAN cannot be encoded to NEON',
);

Assert::same(
	'"special \u0000 chars"',
	Neon::encode("special \x00 chars", true),
);

Assert::same(
	"\"\"\"\n\tspecial\\r\n\tchars\n\"\"\"",
	Neon::encode("special\r\nchars", true),
);

Assert::same(
	"inner:\n    msg: '''\n        string\n        with newline\n    '''\n\n",
	Neon::encode(['inner' => ['msg' => "string\nwith newline"]], true, '    '),
);
