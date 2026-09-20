<?php declare(strict_types=1);

/**
 * Test: the encoder and the values of PHP it accepts.
 */

use Nette\Neon\Entity;
use Nette\Neon\Exception;
use Nette\Neon\Neon;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


class Point
{
	public int $x = 1;
	public ?Point $next = null;
	protected int $hidden = 2;
}


test('objects are maps of their public properties, iterators are read once', function () {
	Assert::same('{x: 1, next: null}', Neon::encode(new Point));
	Assert::same('{}', Neon::encode(new stdClass));
	Assert::same("x: 1\nnext: null\n", Neon::encode(new Point, true));

	$reads = 0;
	$generator = function () use (&$reads) {
		$reads++;
		yield 'a' => [1, 2];
		yield 'b' => 3;
	};
	Assert::same("a:\n\t- 1\n\t- 2\n\nb: 3\n", Neon::encode($generator(), true));
	Assert::same(1, $reads);
	Assert::same('[a, b]', Neon::encode(new ArrayIterator(['a', 'b'])));
	Assert::same('{1: a}', Neon::encode(new ArrayIterator([1 => 'a'])));
	Assert::same('[]', Neon::encode(new ArrayIterator([])));
});


test('what cannot be encoded fails with the path to it', function () {
	Assert::exception(fn() => Neon::encode(['a' => [fn() => 1]]), Exception::class, 'Cannot encode: Closure cannot be encoded at a.0');
	Assert::exception(fn() => Neon::encode(['a' => STDIN]), Exception::class, 'Cannot encode: resource (stream) cannot be encoded at a');
	Assert::exception(fn() => Neon::encode(["\x80" => 1]), Exception::class, 'Cannot encode: a string is not valid UTF-8');
	Assert::exception(fn() => Neon::encode(new Entity("\x80")), Exception::class, 'Cannot encode: a string is not valid UTF-8');
	Assert::exception(fn() => Neon::encode(['x' => new Entity('a', ["\x80"])]), Exception::class, 'Cannot encode: a string is not valid UTF-8 at x.0');

	$pairs = function () {
		yield 1 => 'a';
		yield '1' => 'b';
	};
	Assert::exception(fn() => Neon::encode($pairs()), Exception::class, "Cannot encode: the iterator gives the key '1' twice");
	$floats = function () {
		yield 1.5 => 'a';
	};
	Assert::exception(fn() => Neon::encode($floats()), Exception::class, 'Cannot encode: a key of an iterator must be int or string, float given');
	Assert::exception(fn() => Neon::encode(new DateTimeImmutable('-0001-01-01')), Exception::class, 'Cannot encode: a date out of the years 0 to 9999');
});


test('entities and chains that would decode as other values fail', function () {
	Assert::exception(fn() => Neon::encode(new Entity(new Entity('a'))), Exception::class, 'Cannot encode: the value of an entity cannot be an entity or a chain');
	Assert::exception(fn() => Neon::encode(new Entity(Neon::Chain, [new Entity('a')])), Exception::class, 'Cannot encode: a chain must hold a list of two or more entities');
	Assert::exception(fn() => Neon::encode(new Entity(Neon::Chain, [new Entity('a'), new Entity('1')])), Exception::class, 'Cannot encode: a link of a chain after the first needs a head written as a literal');
	Assert::same('a()b(1)', Neon::encode(new Entity(Neon::Chain, [new Entity('a'), new Entity('b', [1])])));
});


test('cycles fail, a shared object does not', function () {
	$point = new Point;
	$point->next = $point;
	Assert::exception(fn() => Neon::encode($point), Exception::class, 'Cannot encode: a cycle cannot be encoded at next');

	$entity = new Entity('a');
	$entity->attributes = [$entity];
	Assert::exception(fn() => Neon::encode($entity), Exception::class, 'Cannot encode: a cycle cannot be encoded at 0');

	$array = ['x' => 1];
	$array['self'] = &$array;
	Assert::exception(fn() => Neon::encode($array), Exception::class, 'Cannot encode: arrays nested deeper than 500 levels at self.self.%a%');

	$shared = new Point;
	Assert::same('[{x: 1, next: null}, {x: 1, next: null}]', Neon::encode([$shared, $shared]));
});


test('500 levels of arrays are encoded, 501 fail', function () {
	$nest = fn(int $n) => array_reduce(range(1, $n - 1), fn($carry) => [$carry], []);
	Assert::same(str_repeat('[', 500) . str_repeat(']', 500), Neon::encode($nest(500)));
	Assert::same($nest(500), Neon::decode(Neon::encode($nest(500), true)));
	Assert::exception(fn() => Neon::encode($nest(501)), Exception::class, 'Cannot encode: arrays nested deeper than 500 levels at 0.0.%a%');
});


test('floats do not depend on the precision settings or the locale', function () {
	$values = [0.1, 0.30000000000000004, 1e25, 1.5e-5, -0.0, 1e16, 123_456_789_012_345_680.0, 5e-324, 1.7976931348623157e308];
	$expected = '[0.1, 0.30000000000000004, 1.0e+25, 1.5e-5, -0.0, 10000000000000000.0, 1.2345678901234568e+17, 5.0e-324, 1.7976931348623157e+308]';
	Assert::same($expected, Neon::encode($values));

	$ini = [ini_get('precision'), ini_get('serialize_precision')];
	ini_set('precision', '5');
	ini_set('serialize_precision', '3');
	$locale = setlocale(LC_ALL, '0');
	setlocale(LC_ALL, 'cs_CZ.UTF-8', 'Czech_Czechia.1250', 'Czech');
	try {
		Assert::same($expected, Neon::encode($values));
	} finally {
		ini_set('precision', (string) $ini[0]);
		ini_set('serialize_precision', (string) $ini[1]);
		setlocale(LC_ALL, (string) $locale);
	}
	Assert::exception(fn() => Neon::encode(['a' => NAN]), Exception::class, 'INF and NAN cannot be encoded to NEON');
});


test('strings that a literal would read otherwise are quoted', function () {
	Assert::same("['-.5', '+.5', '2020-01-01', '0x10', a b, '', ' a', 'a ', 'a:', a:b, 'a #', \"\\u0000\"]", Neon::encode(['-.5', '+.5', '2020-01-01', '0x10', 'a b', '', ' a', 'a ', 'a:', 'a:b', 'a #', "\x00"]));
	Assert::same("\u{FEFF}a", Neon::decode(Neon::encode("\u{FEFF}a")));
	Assert::same("'\u{FEFF}a'", Neon::encode("\u{FEFF}a"));
});
