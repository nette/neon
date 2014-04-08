<?php

/**
 * Test: Nette\Utils\Neon::decode simple values.
 *
 * @author     David Grudl
 */

use Nette\Utils\Neon,
	Tester\Assert;


require __DIR__ . '/../bootstrap.php';


Assert::null( Neon::decode('') );
Assert::null( Neon::decode('   ') );
Assert::same( 0, Neon::decode('0') );
Assert::same( 0.0, Neon::decode('0.0') );
Assert::same( 1, Neon::decode('1') );
Assert::same( -1.2, Neon::decode('-1.2') );
Assert::same( -120.0, Neon::decode('-1.2e2') );
Assert::true( Neon::decode('true') );
Assert::null( Neon::decode('null') );
Assert::same( 'the"string#literal', Neon::decode('the"string#literal') );
Assert::same( 'the"string', Neon::decode('the"string #literal') );
Assert::same( "the'string #literal", Neon::decode('"the\'string #literal"') );
Assert::same( 'the"string #literal', Neon::decode("'the\"string #literal'") );
Assert::same( '@', Neon::decode('"\u0040"') );
Assert::same( "\xC4\x9B", Neon::decode('"\u011B"') );
Assert::same( "\xf0\x90\x90\x81", Neon::decode('"\uD801\uDC01"') ); // U+10401 encoded as surrogate pair
Assert::same( '<literal> <literal>', Neon::decode('<literal> <literal>') );
Assert::same( "", Neon::decode("''") );
Assert::same( "", Neon::decode('""') );
Assert::same( ':a', Neon::decode(':a') );
Assert::same( 'x', Neon::decode('x') );
Assert::same( "x", Neon::decode("\nx\n") );
Assert::same( "x", Neon::decode("  x") );
Assert::same( "@x", Neon::decode("@x") );
Assert::same( "@true", Neon::decode("@true") );
Assert::same( 'a', Neon::decode('a                                     ') );
Assert::same( 'a', Neon::decode("\xEF\xBB\xBFa") );
