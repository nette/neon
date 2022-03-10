<?php

declare(strict_types=1);

use Nette\Neon\Neon;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


Assert::same(
	[true, true, false, false],
	@Neon::decode('[On, on, Off, off]'),
);

Assert::error(function () {
	Neon::decode('[On, on, Off, off]');
}, [
	[E_USER_DEPRECATED, "Neon: keyword 'On' is deprecated, use true/yes or false/no."],
	[E_USER_DEPRECATED, "Neon: keyword 'on' is deprecated, use true/yes or false/no."],
	[E_USER_DEPRECATED, "Neon: keyword 'Off' is deprecated, use true/yes or false/no."],
	[E_USER_DEPRECATED, "Neon: keyword 'off' is deprecated, use true/yes or false/no."],
]);
