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

// failing test
$examples = array(
    //'Valid ASCII' => "a",
    //'Valid 2 Octet Sequence' => "\xc3\xb1",
    'Invalid 2 Octet Sequence' => "\xc3\x28",
    'Invalid Sequence Identifier' => "\xa0\xa1",
    //'Valid 3 Octet Sequence' => "\xe2\x82\xa1",
    'Invalid 3 Octet Sequence (in 2nd Octet)' => "\xe2\x28\xa1",
    'Invalid 3 Octet Sequence (in 3rd Octet)' => "\xe2\x82\x28",
    //'Valid 4 Octet Sequence' => "\xf0\x90\x8c\xbc",
    'Invalid 4 Octet Sequence (in 2nd Octet)' => "\xf0\x28\x8c\xbc",
    'Invalid 4 Octet Sequence (in 3rd Octet)' => "\xf0\x90\x28\xbc",
    'Invalid 4 Octet Sequence (in 4th Octet)' => "\xf0\x28\x8c\x28",
    'Valid 5 Octet Sequence (but not Unicode!)' => "\xf8\xa1\xa1\xa1\xa1",
    'Valid 6 Octet Sequence (but not Unicode!)' => "\xfc\xa1\xa1\xa1\xa1\xa1",
);

foreach ($examples as $label => $s) {
	echo $label . "\n";
	Assert::same("", Neon::decode('"' . $s . '"'));
}
