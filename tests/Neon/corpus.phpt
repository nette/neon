<?php declare(strict_types=1);

/**
 * Test: the conformance corpus of the specification.
 */

use Nette\Neon\{Exception, Neon};
use Tester\Assert;
use Tests\{Corpus, Dumper, Typed};

require __DIR__ . '/../bootstrap.php';


function error(string $input): string
{
	try {
		Neon::decode($input);
	} catch (Exception $e) {
		return $e->getDescription() . "\n" . $e->position?->line . ':' . $e->position?->column . "\n";
	}
	return 'accepted';
}


test('decode', function () {
	foreach (Corpus::load('decode') as $case) {
		if ($case->runsHere()) {
			$input = (string) $case->read('.neon');
			$document = Neon::parse($input);
			Assert::same($input, $document->print(), $case->name);
			$expected = Typed::toPhp(json_decode((string) $case->read('.json'), true, flags: JSON_THROW_ON_ERROR));
			$actual = $document->toValue();
			Assert::true(Typed::same($expected, $actual), $case->name . ': ' . var_export($actual, true));
		}
	}
});


foreach (['cst', 'roundtrip'] as $category) {
	test($category, function () use ($category) {
		foreach (Corpus::load($category) as $case) {
			if ($case->runsHere()) {
				$input = (string) $case->read('.neon');
				$document = Neon::parse($input);
				Assert::same($input, $document->print(), $case->name);
				Assert::same($case->read('.dump'), Dumper::dump($document), $case->name);
				if ($category === 'roundtrip') {
					$document->toValue();
				}
			}
		}
	});
}


test('encode', function () {
	foreach (Corpus::load('encode') as $case) {
		if ($case->runsHere()) {
			$value = Typed::toEncodable(json_decode((string) $case->read('.json'), true, flags: JSON_THROW_ON_ERROR));
			$indentation = $case->meta['indentation'] ?? "\t";
			$depth = $case->meta['blockMode'] ?? true;
			$blockMode = is_int($depth) ? fn($v, array $path) => count($path) < $depth : $depth;
			if (($error = $case->read('.error')) !== null) {
				Assert::exception(fn() => Neon::encode($value, false, $indentation), Exception::class, trim($error));
				Assert::exception(fn() => Neon::encode($value, $blockMode, $indentation), Exception::class, trim($error));
				continue;
			}
			Assert::same($case->read('.neon'), Neon::encode($value, false, $indentation), $case->name);
			Assert::same($case->read('.block.neon'), Neon::encode($value, $blockMode, $indentation), $case->name . ' block');
		}
	}
});


test('errors', function () {
	foreach (Corpus::load('errors') as $case) {
		if ($case->runsHere()) {
			Assert::same($case->read('.error'), error((string) $case->read('.neon')), $case->name);
		}
	}
});
