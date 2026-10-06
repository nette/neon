# To My Agents!

It is my fervent wish that this file guide every AI coding agent working with code in this repository.

## Documentation

Any distilled, agent-facing documentation for this package - how it works
internally and the rationale behind key design decisions - lives in `docs/`.
Consult it before non-trivial changes; it is the source of truth from which the
public manual is distilled.

Read `docs/internals.md` before editing the lexer, the parser, the tree, the
journal or the editor: the lossless tree, the raw writes and the rollback share
one set of invariants that a local change easily breaks.

The format itself is defined by the specification in `tests/neon-spec` (checked
out by `composer spec` from `github.com/nette/neon-specification` at the commit pinned in
`tests/neon-spec.lock`): grammar, model, values, editing,
errors and the linter contract, with the conformance corpus. A change of
behaviour starts there, not here.

`docs/upgrading.md` records, per released version, every change visible to
users of the package (renames, removals, deprecations, signatures, behavior,
configuration), each with its commit hash; internal changes are not in it. It
is the place to look up the history of a public symbol. Do not write to it; it
is extended at release time from the git history.

## Project Overview

**Nette NEON** reads, writes and edits the NEON format (Nette Object Notation) - a
human-readable configuration format, YAML-like but with entities. JSON is a subset
of NEON. Version 4.0 parses into a lossless syntax tree: printing it gives the input
back byte for byte, and edits change only the lines they touch.

- **PHP Version**: 8.4 - 8.6
- **Package**: `nette/neon` (`ext-json`, `ext-mbstring`)

## Essential Commands

```bash
# Check out the specification and its corpus
composer spec

# Run all tests
composer tester
vendor/bin/tester tests/Neon/ -s -C

# Wider fuzzing of edits
FUZZ_SEEDS=30000 vendor/bin/tester tests/Neon/mutation-fuzz.phpt -s

# Static analysis (PHPStan level max)
composer phpstan

# Lint NEON files
bin/neon-lint <path>
```

## Conventions

- Every file starts with `declare(strict_types=1);`; **tabs**; single quotes unless
  escaping is needed; classes are `final` unless designed for extension
  (`Evaluator` is). Nette Coding Standard.
- The public API is the `Neon` facade, `Entity`, `Exception`, `Position`, `Token`,
  `Trivia`, `Node` and `Nodes\*`, `Evaluator`, `Traverser`, `Printer` and the
  enums; everything else is `@internal`.
- **Kinds of tokens and trivia are int constants** of `Token` and `Trivia`, not
  enums; an enum only where a kind is passed as a type. Enum cases get no comment.
- **No references to the specification in code**: comments, docblocks, test
  names and messages say what the code does in their own words, never cite a
  chapter of the spec. The spec is cited in docs and commit messages.
- **A property is a stored value, a method is work**: positions, printing,
  values and searches are methods; no computing get hooks.
- Tests are Nette Tester `.phpt`; `Assert::exception` message patterns use `%a%`
  (any-text wildcard). Every test of a refused edit checks with a snapshot that
  nothing changed.

## Working in this repo

- **Never write to the tree past the public methods** except through the raw
  `@internal` writes (`Node::writeParent()`, `writeSlot()`, `Token::writeParent()`,
  `writeLeadingTrivia()`, `writeTrailingTrivia()`). They record their undo in the
  journal; a direct assignment does not, and a refused edit would then leave a
  trace.
- **A public edit runs in `Journal::atomic()`** and builds every new node before it
  touches the tree; no user code may run while the tree is written.
- **Trivia are immutable and shared between tokens**; never mutate one, and keep an
  instance at most once in one list.
- **Decoding stays one path**: `decode()` is parse plus `Evaluator`. Do not add a
  faster parser with other semantics.
- **The behaviour of 3.x is kept where users see it**: the values of `decode()`, the
  BC tests (`Decoder.*.phpt`, `Encoder.phpt`). Deviations are deliberate and listed
  in the specification.
- User-facing format documentation (syntax rules, entities, literals, encode/decode
  usage) is manual material and lives in the public web docs, not here.
