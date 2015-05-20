<?php

/**
 * Test: Nette\Neon\Neon::encode.
 */

use Nette\Neon\Neon,
	Nette\Neon\Entity,
	Tester\Assert;


require __DIR__ . '/../bootstrap.php';


Assert::same(
	'[true, "TRUE", "tRuE", "true", false, "FALSE", "fAlSe", "false", null, "NULL", "nUlL", "null", "yes", "no", "on", "off"]',
	Neon::encode([
		TRUE, 'TRUE', 'tRuE', 'true',
		FALSE, 'FALSE', 'fAlSe', 'false',
		NULL, 'NULL', 'nUlL', 'null',
		'yes', 'no', 'on', 'off',
]) );

Assert::same(
	'[1, 1.0, 0, 0.0, -1, -1.2, "1", "1.0", "-1"]',
	Neon::encode([1, 1.0, 0, 0.0, -1, -1.2, '1', '1.0', '-1'])
);

Assert::same(
	'["[", "]", "{", "}", ":", ": ", "=", "#"]',
	Neon::encode(['[', ']', '{', '}', ':', ': ', '=', '#'])
);

Assert::same(
	'[1, 2, 3]',
	Neon::encode([1, 2, 3])
);

Assert::same(
	'{1: 1, 2: 2, 3: 3}',
	Neon::encode([1 => 1, 2, 3])
);

Assert::same(
	'{foo: 1, bar: [2, 3]}',
	Neon::encode(['foo' => 1, 'bar' => [2, 3]])
);

Assert::same(
	'item(a, b)',
	Neon::encode(Neon::decode('item(a, b)'))
);

Assert::same(
	'item<item>(a, b)',
	Neon::encode(Neon::decode('item<item>(a, b)'))
);

Assert::same(
	'item(foo: a, bar: b)',
	Neon::encode(Neon::decode('item(foo: a, bar: b)'))
);

Assert::same(
	'[]()',
	Neon::encode(Neon::decode('[]()'))
);

$entity = new Entity('ent');
$entity->attributes = NULL;
Assert::same(
	'ent()',
	Neon::encode($entity)
);

Assert::same(
	PHP_VERSION_ID >= 50400 ? '",žlu/ťoučký"' : '",\u017elu\/\u0165ou\u010dk\u00fd"',
	Neon::encode(',žlu/ťoučký')
);

Assert::same(
	"foo: 1\nbar:\n\tx:\n\t\t- 1\n\t\t- 2\n\n\ty:\n\t\t- 3\n\t\t- 4\n\nbaz: null\n",
	Neon::encode(['foo' => 1, 'bar' => ['x' => [1, 2], 'y' => [3, 4]], 'baz' => NULL], Neon::BLOCK)
);
