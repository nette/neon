<?php

/**
 * Test: Nette\Neon\Neon::decode multi lines string
 */

declare(strict_types=1);

use Nette\Neon\Neon;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


// double quoted
Assert::same(
	"multi\nlines",
	Neon::decode('"""
multi
lines
"""'),
);

Assert::same(
	"multi\"\"\"\nlines'''",
	Neon::decode('"""
multi"""
lines\'\'\'
"""'),
);

// single quoted
Assert::same(
	"multi\"\"\"\nlines'''",
	Neon::decode("'''
multi\"\"\"
lines'''
'''"),
);

// ignore space before end
Assert::same(
	"multi\nlines",
	Neon::decode("'''
multi
lines
\t '''"),
);

// require new line after start
Assert::exception(function () {
	Neon::decode("'''multi\nlines\n'''");
}, Nette\Neon\Exception::class);

Assert::exception(function () {
	Neon::decode("''' multi\nlines\n'''");
}, Nette\Neon\Exception::class);

// require new line before end
Assert::exception(function () {
	Neon::decode("'''\nmulti\nlines'''");
}, Nette\Neon\Exception::class);

Assert::exception(function () {
	Neon::decode("'''\nmulti\nlines\t '''");
}, Nette\Neon\Exception::class);

// removing indentation
Assert::same(
	"multi\nlines\n\tstring",
	Neon::decode("'''
\tmulti
lines
\t\tstring
'''"),
);

Assert::same(
	"multi\nlines\n\t\t string",
	Neon::decode("'''
\t multi
\t lines
\t\t string
\t\t'''"),
);

// first empty line
Assert::same(
	"\nmulti\nlines",
	Neon::decode("'''

\t multi
\t lines
\t\t'''"),
);

// last empty line
Assert::same(
	"multi\nlines\n",
	Neon::decode("'''
\t multi
\t lines

\t\t'''"),
);

// escaping
Assert::same(
	"\t multi\n\t lines",
	Neon::decode('"""
\t multi
\t lines
"""'),
);

//no content
Assert::same(
	'',
	Neon::decode('"""
"""'),
);

Assert::same(
	'',
	Neon::decode("'''
'''"),
);

Assert::exception(function () {
	Neon::decode('"""
\t multi
\t lines
\t\t"""');
}, Nette\Neon\Exception::class);

// complex usage
Assert::same(
	['a' => "multi\nlines"],
	Neon::decode("
a: '''
	multi
	lines
	'''
"),
);

Assert::same(
	['a' => "multi\nlines"],
	Neon::decode("
a: '''
	multi
	lines
	'''
"),
);

Assert::same(
	["multi\nlines" => "multi\nlines"],
	Neon::decode("
'''
	multi
	lines
	''': '''
	multi
	lines
	'''
"),
);
