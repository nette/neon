[![NEON](https://github.com/nette/neon/assets/194960/9d3c809d-0a60-4ff0-a54a-bbb25273a8a8)](https://neon.nette.org)

[![Downloads this Month](https://img.shields.io/packagist/dm/nette/neon.svg)](https://packagist.org/packages/nette/neon)
[![Tests](https://github.com/nette/neon/workflows/Tests/badge.svg?branch=master)](https://github.com/nette/neon/actions)
[![Coverage Status](https://coveralls.io/repos/github/nette/neon/badge.svg?branch=master)](https://coveralls.io/github/nette/neon?branch=master)
[![Latest Stable Version](https://poser.pugx.org/nette/neon/v/stable)](https://github.com/nette/neon/releases)
[![License](https://img.shields.io/badge/license-New%20BSD-blue.svg)](https://github.com/nette/neon/blob/master/license.md)

 <!---->

<h3>

✅ A NEON parser that keeps [every comment and space](#the-tree-nodes-slots-tokens-trivia)<br>
✅ [Edit a configuration file](#format-preserving-editing) and the diff is only your change<br>
✅ [Your own values](#your-own-values-the-evaluator) for entities, constants or parameters<br>
✅ [`neon-lint`](#for-editors-ci-and-ai-agents) for editors, CI and AI agents

</h3>

 <!---->

**NEON parser, encoder and lossless editor for PHP.** NEON is the human-friendly configuration
format of the Nette framework, a YAML alternative with entities. This library parses it into a
concrete syntax tree in which every character of the file has its place, so printing the tree
gives the file back byte for byte:

```php
$document = Nette\Neon\Neon::parse($text);
$document->print() === $text; // true, for any input NEON accepts
```

That is what makes editing safe. Change a value, add a key, remove an item, and the rest of the
file stays as its author wrote it:

```php
$document->setValue(['database', 'host'], 'db.example.com');
```

```
- 	host: localhost   # the server
+ 	host: db.example.com   # the server
```

On that you build configuration editors and admin panels, installers that register an extension
in `config.neon`, migrations that rename a service, and AI agents that change configuration
without wrecking it.

 <!---->

[Support Neon](https://github.com/sponsors/dg)
----------------------------------------------

Do you like NEON? Are you looking forward to the new features?

[![Buy me a coffee](https://files.nette.org/icons/donation-3.svg)](https://github.com/sponsors/dg)

Thank you!

 <!---->

NEON in one minute
==================

```neon
# mappings and sequences, indented by tabs or spaces
database:
	host: localhost
	port: 3306

roles: [guest, member, admin]   # inline arrays

# entities: a value with attributes, the way Nette writes services
mailer: App\Mailer(@smtp, timeout: 30)

note: '''
	multiline strings
	without escaping
'''
```

JSON is a subset of NEON. The format is described in the [manual](https://doc.nette.org/en/neon/format),
and you can [try it in the sandbox](https://fiddle.nette.org/neon/).

▶ Runnable examples: [examples/edge-cases](examples/edge-cases/)

 <!---->

Installation
============

```shell
composer require nette/neon
```

It requires PHP 8.4 up to 8.6 with the `json` and `mbstring` extensions.

 <!---->

Decoding and encoding
=====================

```php
use Nette\Neon\Neon;

$value = Neon::decode('hello: world');       // ['hello' => 'world']
$value = Neon::decodeFile('config.neon');
$neon = Neon::encode(['a' => [1, 2]]);       // '{a: [1, 2]}'
$neon = Neon::encode(['a' => [1, 2]], true); // "a:\n\t- 1\n\t- 2\n\n"
$neon = Neon::encode($config, fn($v, $path) => !$path); // blocks for the first level only
```

| NEON | PHP |
|---|---|
| `null`, `true`, `yes` ... | `null`, `bool` |
| integer | `int`, beyond the 64-bit range a string of its digits |
| float | `float` |
| string | `string` |
| date | `DateTimeImmutable`; an invalid date such as `2024-02-30` stays a string |
| entity, chain | `Nette\Neon\Entity` |
| list, map | `array` |

Errors throw `Nette\Neon\Exception` with the position of the problem. What `encode()` cannot write
without loss, such as invalid UTF-8, `INF` or a cycle, is an error that names the path to the value.

▶ Runnable examples: [examples/parsing](examples/parsing/), [examples/encoding](examples/encoding/)

 <!---->

Format-preserving editing
=========================

Every edit changes the tokens it edits and nothing else, and the guarantees of the specification
(the file reads back as the same tree, with the expected value, with the change only where it was
made) are checked after every operation of a large corpus of edits.

```php
use Nette\Neon\CommentPolicy;
use Nette\Neon\Neon;

$document = Neon::parse($text);
$document->setValue(['database', 'port'], 3307);          // 0x0CEA stays hexadecimal: 0x0CEB
$document->setValue(['database', 'debug'], false);        // yes becomes no, not false
$document->setValue(['timeout'], 30);                     // the same value: no change at all
$document->setValue(['parameters', 'admin'], 'a@b.cz');   // a new key, indented like its siblings
$document->addItem(['services'], 'App\OrderFacade');      // a new item of a list
$document->removeItem(['database', 'driver']);            // with the comment that describes it
$document->removeItem(['mail'], CommentPolicy::MoveToNextToken);
file_put_contents('config.neon', $document->print());
```

A missing key in the middle of a path is an error, so a typo does not create a new section.
Removing the last item of a collection leaves it empty (`services: []`), because in a
configuration empty and missing often mean different things; an item marked `nullable` loses its
value instead. A refused edit changes nothing, not even half of itself.

▶ Runnable examples: [examples/editing](examples/editing/), [examples/config-editor](examples/config-editor/)

 <!---->

The tree: nodes, slots, tokens, trivia
======================================

```php
$item = $document->findItem(['database', 'user']);
$item->getValue();                      // 'root'
$item->getTrailingComments();           // the comment on its line
$item->value->getFirstToken()->text;    // the text exactly as written
$item->getPosition();                   // line, column and offset, computed from the current tree
```

Eight node classes (`DocumentNode`, `BlockArrayNode`, `InlineArrayNode`, `ArrayItemNode`,
`EntityNode`, `EntityChainNode`, `StringNode`, `LiteralNode`) with named slots, tokens with their
trivia (whitespace, line endings, comments), `find()`, `findAncestor()`, siblings, and
`Traverser` for a walk with replacements.

▶ Runnable examples: [examples/tree](examples/tree/)

 <!---->

Your own values: the Evaluator
==============================

`Neon::decode()` is the tree projected onto PHP values by `Nette\Neon\Evaluator`. A subclass
changes how literals, strings, entities and items become values, while the keys and the order of
the arrays stay those of the document; that is how the DI container of Nette reads its
configuration.

```php
final class ConstantEvaluator extends Nette\Neon\Evaluator
{
	protected function evaluateLiteral(Nette\Neon\Nodes\LiteralNode $node, Nette\Neon\Place $place): mixed
	{
		$value = parent::evaluateLiteral($node, $place);
		return is_string($value) && defined($value) ? constant($value) : $value;
	}
}

$config = new ConstantEvaluator()->evaluate(Nette\Neon\Neon::parse($text)->value);
```

▶ Runnable examples: [examples/evaluator](examples/evaluator/)

 <!---->

For editors, CI and AI agents
=============================

```shell
vendor/bin/neon-lint config/
```

```
config/local.neon:3:2: Duplicated key 'user'
```

One line per finding, the exit code as the verdict (0 valid, 1 findings, 2 a failure of the tool),
`--json` for tools, `--check` to verify that the file reads back byte for byte. An AI agent that
runs it after it saves a `.neon` file fixes a broken one before it goes on.

▶ Runnable examples: [examples/linting](examples/linting/)

 <!---->

One format, one specification
=============================

The format, the value model, the tree and the semantics of editing are written down in a
language-neutral [specification](https://github.com/nette/neon-specification) with a conformance corpus,
and every implementation runs that corpus in its tests. The same specification is implemented
for JavaScript and TypeScript by [@nette/neon](https://github.com/nette/neon-js); the two
implementations were checked against each other and against nette/neon 3.4 over tens of
thousands of real-world files.

 <!---->

Upgrading from 3.x
==================

`Neon::decode()`, `decodeFile()` and `encode()` work as before and give the same values. What
changed:

- it requires PHP 8.4 or newer with the `mbstring` extension;
- some error messages are more precise, so code that matches their text may need a change;
- the tree is new: `Neon::parse()` gives a lossless `DocumentNode`; `Decoder`,
  `Node\*` and the old `Traverser` are gone; the constants `Neon::BLOCK` and `Neon::CHAIN` are
  deprecated (use `blockMode: true` and `Neon::Chain`);
- `encode()` refuses invalid UTF-8 instead of replacing it, and writes floats and dates without
  loss;
- a subclass of `Evaluator` that projects two different keys of one array onto the same key gets
  an error, not a silently overwritten value.

 <!---->

Limits
======

- No formatter: the library keeps your formatting, it does not impose one.
- No schema or validation of values.
- No error recovery: an invalid file is reported, not partially parsed.
- Positions are computed on request, from the current tree.

 <!---->

Documentation
=============

The [examples](examples/) are the documentation of the API, each chapter with the real output of
its programs. The format itself is documented in the [Nette manual](https://doc.nette.org/en/neon).
