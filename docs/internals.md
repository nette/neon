# Neon internals

How `nette/neon` decodes and encodes, for agents editing it. Medium depth, one
file: the stages (lexer → token stream → parser → node AST → encoder) share one
context and the traps cluster around tokenization and round-tripping.

## Pipeline and the AST as the round-trip pivot

`Decoder` = tokenize → parse → `toValue()`; `Encoder` = `valueToNode()` →
`toString()`. The **node AST is the pivot**: decoding builds nodes that retain
formatting (delimiters, indentation, token positions), and `parseToNode()` exposes
them so a tree can be edited and re-serialized **format-preservingly**. Encoding
goes the other way, synthesizing fresh nodes from a plain PHP value with no
formatting history. Round-trip fidelity is a real goal (guarded by a fuzz test),
not incidental.

## The token stream keeps everything; node indices point into it

The `Lexer` emits **every** token, including trivia — `Whitespace`, `Comment`,
`Newline`. Nothing is dropped; that is what makes format preservation possible.
The consequence is the package's central trap:

- **`Node::$startTokenPos` / `$endTokenPos` are indices into the FULL
  `TokenStream::$tokens` array** (trivia included), not into a significant-only
  view. Any code that walks tokens by index must account for interleaved
  whitespace/comments.
- Nodes also carry a separate source `Position` (`$start`/`$end`: line, column,
  offset). `$end` is set from the token *after* the start index and is
  best-effort — do not treat it as an exact span.

## The parser is lookahead, not backtracking

`TokenStream` is consumed with a peek/consume idiom, and understanding the two
methods is essential:

- **`is(...$kind)` is a trivia-skipping peek.** It advances `$index` **past**
  whitespace/comments to the next significant token, then tests it — but does
  **not** consume that significant token. Calling it repeatedly lands on the same
  place; it has no logical side effect on the token you are inspecting. (An earlier
  design moved the index on match; the current contract does not.)
- **`tryConsume(...$kind)` = `is()` + advance on match.** It returns the token and
  steps over it only when the kind matches, else returns null.
- **`getIndentation()` reads BACKWARD through trivia.** Block structure is decided
  from the `Whitespace` token that sits *immediately after* a `Newline` (or at the
  start): it inspects `tokens[index-2]` (must be a newline or the start) and
  `tokens[index-1]` (must be whitespace). This only works because `is`/`tryConsume`
  left `$index` just past that whitespace.

There is **one deliberate backtrack**: after a `-` bullet that opens a new block,
the parser tries a tab-indented sub-block and, on failure, `seek()`s back and
retries with a two-space indent (`parseBlock`). `seek()` survives solely for this
tab-vs-space ambiguity; do not reintroduce general backtracking.

Two other parser subtleties: a keyed item followed by `-` opens a **"special dash
subblock"** (`onlyBullets`), and block indentation is stored relative to the
parent (a child `BlockArrayNode`'s `indentation` has the parent prefix stripped).

## Multiline string indentation is the round-trip corruption source

`StringNode::parse` for a `'''`/`"""` block takes the **whitespace prefix of the
first content line as the indentation** and strips exactly that prefix from every
line. The trap: **if the string value itself begins with whitespace, that leading
whitespace is swallowed as indentation and the round-trip silently corrupts the
value.** This single mechanism accounts for the only remaining round-trip
mismatches the fuzz test finds; treat it as a known open bug, not a puzzle to
re-diagnose (`docs/local/ideas/multiline-string-indentation.md`).

`StringNode::toString` picks the delimiter by content: single-line strings become
single-quoted (`''` escaped) unless they contain control characters (then
JSON-style double quotes); multiline strings become `'''` unless control chars or
an embedded `'''` force `"""` with escaping. Indentation is re-applied per line.

## Lexer and encoder share one grammar

- The `Lexer` is a **single anchored alternation** of the `Patterns` (`~(...)|...~Amixu`);
  the token type is derived from **which capture group matched**
  (`count($match) - 2`), and source `Position` is advanced manually per token. A
  leftover offset at the end (nothing matched the tail) raises the error; `\r` is
  stripped up front.
- **The encoder reuses the lexer's literal grammar to decide quoting.**
  `Lexer::requiresDelimiters()` quotes a string when it holds control chars, looks
  like a number/bool/null keyword, **or fails to match the `Literal` pattern in
  full**. So "can this be written bare?" is answered by the same regex that lexes
  bare literals — a non-local coupling: change the `Literal` pattern and encoder
  output shifts too.

## Error taxonomy

All lexing and parsing failures throw **`Nette\Neon\Exception`** carrying a
`Position` (Lexer unmatched input / invalid UTF-8, `TokenStream::error`, string
escape errors). Encoding is more lenient: an **invalid UTF-8 string is not an
exception** — the encoder emits an `E_USER_WARNING` and substitutes U+FFFD.
(A dedicated `SyntaxErrorException` subclass is a proposed idea, not yet in code —
do not assume it exists.)

## Navigation map

| Concern | Where |
|---|---|
| Tokenization, one-regex grammar | `Lexer::tokenize`, `Lexer::Patterns` |
| Full-stream + peek/consume/indentation | `TokenStream` (`is`, `tryConsume`, `getIndentation`) |
| Block/inline parse, dash backtrack | `Parser::parseBlock`, `parseBraces`, `parseValue` |
| Token index ↔ source position | `Parser::injectPos`, `Node` position fields |
| Multiline string trap, delimiter choice | `StringNode::parse`, `toString` |
| Value → node, bare-vs-quoted | `Encoder::valueToNode`, `Lexer::requiresDelimiters` |
