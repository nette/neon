<?php declare(strict_types=1);

/**
 * Test: the conformance corpus of the specification.
 */

use Nette\Neon\{Exception, Neon};
use Tester\Assert;
use Tests\{Corpus, Typed};

require __DIR__ . '/../bootstrap.php';


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
