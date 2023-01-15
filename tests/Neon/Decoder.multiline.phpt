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
	Neon::decode(<<<'XX'
		"""
		multi
		lines
		"""
		XX),
);

Assert::same(
	"multi\"\"\"\nlines'''",
	Neon::decode(<<<'XX'
		"""
		multi"""
		lines'''
		"""
		XX),
);

// single quoted
Assert::same(
	"multi\"\"\"\nlines'''",
	Neon::decode(<<<'XX'
		'''
		multi"""
		lines'''
		'''
		XX),
);

// ignore space before end
Assert::same(
	"multi\nlines",
	Neon::decode(<<<XX
		'''
		multi
		lines
		\t '''
		XX),
);

// require new line after start
Assert::exception(
	fn() => Neon::decode("'''multi\nlines\n'''"),
	Nette\Neon\Exception::class,
);

Assert::exception(
	fn() => Neon::decode("''' multi\nlines\n'''"),
	Nette\Neon\Exception::class,
);

// require new line before end
Assert::exception(
	fn() => Neon::decode("'''\nmulti\nlines'''"),
	Nette\Neon\Exception::class,
);

Assert::exception(
	fn() => Neon::decode("'''\nmulti\nlines\t '''"),
	Nette\Neon\Exception::class,
);

// removing indentation
Assert::same(
	"multi\nlines\n\tstring",
	Neon::decode(<<<XX
		'''
		\tmulti
		lines
		\t\tstring
		'''
		XX),
);

Assert::same(
	"multi\nlines\n\t\t string",
	Neon::decode(<<<XX
		'''
		\t multi
		\t lines
		\t\t string
		\t\t'''
		XX),
);

// first empty line
Assert::same(
	"\nmulti\nlines",
	Neon::decode(<<<XX
		'''

		\t multi
		\t lines
		\t\t'''
		XX),
);

// last empty line
Assert::same(
	"multi\nlines\n",
	Neon::decode(<<<XX
		'''
		\t multi
		\t lines

		\t\t'''
		XX),
);

// escaping
Assert::same(
	"\t multi\n\t lines",
	Neon::decode(<<<'XX'
		"""
		\t multi
		\t lines
		"""
		XX),
);

//no content
Assert::same(
	'',
	Neon::decode(<<<'XX'
		"""
		"""
		XX),
);

Assert::same(
	'',
	Neon::decode(<<<'XX'
		'''
		'''
		XX),
);

Assert::exception(
	fn() => Neon::decode(<<<'XX'
		"""
		\t multi
		\t lines
		\t\t"""
		XX),
	Nette\Neon\Exception::class,
);

// complex usage
Assert::same(
	['a' => "multi\nlines"],
	Neon::decode(<<<'XX'

		a: '''
			multi
			lines
			'''

		XX),
);

Assert::same(
	['a' => "multi\nlines"],
	Neon::decode(<<<'XX'

		a: '''
			multi
			lines
			'''

		XX),
);

Assert::same(
	["multi\nlines" => "multi\nlines"],
	Neon::decode(<<<'XX'
		'''
			multi
			lines
			''': '''
			multi
			lines
			'''

		XX),
);
