# To My Agents!

It is my fervent wish that this file guide every AI coding agent working with code in this repository.

## Documentation

Any distilled, agent-facing documentation for this package - how it works
internally and the rationale behind key design decisions - lives in `docs/`.
Consult it before non-trivial changes; it is the source of truth from which the
public manual is distilled.

The decode/encode pipeline shares one context and the traps cluster around
tokenization and round-tripping (the full token stream, the lookahead parser, the
multiline-string corruption bug). Read `docs/internals.md` before editing them.

## Project Overview

**Nette NEON** parses and encodes the NEON format (Nette Object Notation) - a
human-readable configuration format, YAML-like but with entities. JSON is a subset
of NEON.

- **PHP Version**: 8.0 - 8.5
- **Package**: `nette/neon` (only dependency: `ext-json`)

## Essential Commands

```bash
# Run all tests
composer tester
vendor/bin/tester tests/Neon/ -s -C

# Static analysis (PHPStan level 6)
composer phpstan

# Lint NEON files
vendor/bin/neon-lint <path>
```

## Conventions

- Every file starts with `declare(strict_types=1);`; **tabs**; single quotes unless
  escaping is needed; classes are `final` unless designed for extension; `@internal`
  for non-public classes (`Decoder` is internal - the `Neon` facade is the entry
  point). Nette Coding Standard.
- Tests are Nette Tester `.phpt`; `Assert::exception` message patterns use `%a%`
  (any-text wildcard).

## Working in this repo

- **The node AST is the editable middle layer.** `parseToNode()` exposes it, and
  each node records `startTokenPos`/`endTokenPos` so it can be mapped back to its
  exact source slice. But `toString()` re-serializes **canonically** - comments are
  dropped and the quote/delimiter style is re-derived from the value, so a
  decode → `toString()` round-trip normalizes rather than preserves the original
  formatting. (Full format preservation is a planned feature, not yet implemented.)
  Encoding synthesizes fresh nodes from a plain PHP value.
- **The token stream keeps EVERYTHING, trivia included** (whitespace, comments,
  newlines). `Node::$startTokenPos`/`$endTokenPos` index into that **full** array -
  any code walking tokens by index must skip interleaved trivia. This is the central
  trap.
- **The parser is lookahead, not backtracking.** `TokenStream::isNext()` skips trivia
  and peeks at the next significant token **without consuming it**; `consume()` is
  `isNext()` + advance on match; `getIndentation()` reads *backward* through trivia.
  The one deliberate `seek()` backtrack handles the dash-subblock tab-vs-two-space
  ambiguity - don't reintroduce general backtracking.
- **Multiline `'''` strings whose value starts with whitespace corrupt on
  round-trip** - the leading whitespace is swallowed as indentation. Known open bug
  (`StringNode::parse`), not a puzzle to re-diagnose.
- **The encoder reuses the lexer's literal grammar** (`requiresDelimiters`) to decide
  quoting - change the `Literal` pattern and encoder output shifts too.
- **All lex/parse errors throw `Nette\Neon\Exception`** (a plain `\Exception`
  subclass) whose message carries `on line N at column N`; there is no structured
  position object. Encoding invalid UTF-8 emits `E_USER_WARNING` + U+FFFD, not an
  exception. There is no `SyntaxErrorException` in the code - don't assume it exists.
- User-facing format documentation (syntax rules, entities, literals, encode/decode
  usage) is manual material and lives in the public web docs, not here.
