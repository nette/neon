# Encoding: from PHP values to NEON

`Neon::encode()` writes a value the way every implementation of NEON writes it, so a file written
by a PHP tool and one written by a JavaScript one look the same, and both decode back to the value.

**You will learn:**

- how arrays, objects, iterators, entities, chains and dates are written
- the inline and the block mode, which arrays are blocks, and the indentation
- which strings are written without quotes, and why

```shell
php examples/encoding/encode.php
```

## Values, inline and as blocks (encode.php)

```
{database: {dsn: 'mysql:host=localhost', user: root, options: {lazy: true}}, services: [App\Mailer(@smtp, timeout: 30)], factory: @builder()::create(1), started: 2016-06-03 19:00:00 +0200, limit: '12345678901234567890', note: '''
	first line
	second line
'''}

database:
	dsn: 'mysql:host=localhost'
	user: root
	options:
		lazy: true

services:
	- App\Mailer(@smtp, timeout: 30)

factory: @builder()::create(1)
started: 2016-06-03 19:00:00 +0200
limit: '12345678901234567890'
note: '''
	first line
	second line
'''

database: {dsn: 'mysql:host=localhost', user: root, options: {lazy: true}}
hosts: [alpha, beta]

database:
	dsn: 'mysql:host=localhost'
	user: root
	options:
		lazy: true

hosts: [alpha, beta]

"plain words"  -> plain words
"yes"          -> 'yes'
"On"           -> 'On'
"1.5"          -> '1.5'
"-.5"          -> '-.5'
"2016-06-03"   -> '2016-06-03'
"a: b"         -> 'a: b'
"a#b"          -> a#b
"a #b"         -> 'a #b'
""             -> ''
" padded"      -> ' padded'
"tab\there"    -> 'tab	here'
"bell\u0007"   -> "bell\u0007"
```

The inline mode writes one line, the block mode one item per line, with a blank line after a
nested block; the argument `indentation` is a tab by default. Which arrays are blocks is yours to
say: a closure `blockMode(value, path)` instead of `true` is asked for every array, here first for
the first level only, then for all but lists. An object is written as the map of its public properties, an iterator as what it yields,
read once. An `Entity` is `value(attributes)`, and a chain, an entity whose value is `Neon::Chain`,
is its links written one after another, as in `@builder()::create(1)`. A date is written with its
offset.

A string is written without quotes only when the lexer reads it as a single literal that evaluates
to the same string: so `yes` gets quotes because it is a boolean, `On` because every spelling of
`true`, `false`, `yes`, `no`, `on`, `off` and `null` is quoted, so that no reader takes it for one,
`1.5` and `-.5` because they are numbers, `a: b` and `a #b` because the colon and the hash would be
syntax there, while `a#b` is a plain literal. A string with a control character is written in
double quotes with escapes. The digits of an integer beyond `PHP_INT_MAX`, which `Neon::decode()`
gives as a string, are a string to the encoder too.

What cannot be written fails with the path to it, never silently: a string that is not valid
UTF-8, a closure, a cycle of objects, arrays nested deeper than 500 levels, `INF` or `NAN`.
