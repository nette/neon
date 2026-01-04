# Neon internals

How `nette/neon` decodes and encodes, for agents editing it. Medium depth, one
file: the stages (lexer → token stream → parser → node AST → encoder) share one
context and the traps cluster around tokenization and round-tripping.

## Pipeline and the AST as the round-trip pivot

`Decoder` = tokenize → parse → `toValue()`; `Encoder` = `valueToNode()` →
`toString()`. The **node AST is the pivot**: `parseToNode()` exposes it, and each
node records `startTokenPos`/`endTokenPos` so it can be mapped back to its exact
source slice (see `Parser.nodes.phpt`). But `toString()` re-serializes
**canonically** — comments are dropped and the quote/delimiter style is re-derived
from the value — so decode → `toString()` normalizes the input rather than
preserving its original formatting. (Full format preservation is a planned feature,
not yet implemented.) Encoding goes
the other way, synthesizing fresh nodes from a plain PHP value with no formatting
history.

## The token stream keeps everything; node indices point into it

The `Lexer` emits **every** token, including trivia — `Whitespace`, `Comment`,
`Newline`. Nothing is dropped; that is what lets a node map back to its original
source slice (and is the groundwork for future format preservation). The
consequence is the package's central trap:

- **`Node::$startTokenPos` / `$endTokenPos` are indices into the FULL
  `TokenStream::$tokens` array** (trivia included), not into a significant-only
  view. Any code that walks tokens by index must account for interleaved
  whitespace/comments.
- Nodes also carry `$startLine` / `$endLine` (plain nullable ints — there is no
  structured `Position` object, no column or offset on the node). `injectPos()`
  fills them from a token-index → line map (`Parser::$posToLine`); `$endLine` is
  read from the token *after* `$endTokenPos` and is best-effort — do not treat it
  as an exact span.

## The parser is lookahead, not backtracking

`TokenStream` is consumed with a peek/consume idiom, and understanding the two
methods is essential:

- **`isNext(...$types)` is a trivia-skipping peek.** It advances `$pos` **past**
  whitespace/comments to the next significant token, then tests it — but does
  **not** consume that significant token. Calling it repeatedly lands on the same
  place; it has no logical side effect on the significant token you are inspecting.
- **`consume(...$types)` = `isNext()` + advance on match.** It returns the token and
  steps over it only when the type matches, else returns null.
- **`getIndentation()` reads BACKWARD through trivia.** Block structure is decided
  from the `Whitespace` token that sits *immediately after* a `Newline` (or at the
  start): it inspects `tokens[$pos-2]` (must be a newline or the start) and
  `tokens[$pos-1]` (must be whitespace). This only works because `isNext`/`consume`
  left `$pos` just past that whitespace.

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
whitespace is swallowed as indentation and the decode → `toString()` round-trip
silently corrupts the value.** Treat it as a known open bug, not a puzzle to
re-diagnose.

`StringNode::toString` picks the delimiter by content: single-line strings become
single-quoted (`''` escaped) unless they contain control characters other than tab
(then JSON-style double quotes); multiline strings become `'''` unless control
chars or an embedded `'''` force `"""` with escaping. Indentation is re-applied per
line. The `'''` detection has a hole: it matches only `'''` preceded by
newline + whitespace (`\n[\t ]+'''`), so a value line starting **directly** with
`'''` keeps the `'''` delimiter — after indentation is prepended, that line reads
as the closing delimiter and the encoded output does not parse back at all. A
second known open bug, also not a puzzle to re-diagnose.

## Lexer and encoder share one grammar

- The `Lexer` is a **single anchored alternation** of the `Patterns` (`~(...)|...~Amixu`);
  the token type is derived from **which capture group matched**
  (`count($match) - 2`). A running `$offset` is accumulated only to detect a
  leftover tail: if it doesn't reach the end of the input (nothing matched the
  rest), the lexer raises the error. Line/column are not tracked here — they are
  reconstructed later from token values (`Parser::initLines`, `TokenStream::error`).
  `\r` is stripped up front.
- **The encoder reuses the lexer's literal grammar to decide quoting.**
  `Lexer::requiresDelimiters()` quotes a string when it holds control chars, looks
  like a number/bool/null keyword, **or fails to match the `Literal` pattern in
  full**. So "can this be written bare?" is answered by the same regex that lexes
  bare literals — a non-local coupling: change the `Literal` pattern and encoder
  output shifts too.

## Error taxonomy

All lexing and parsing failures throw **`Nette\Neon\Exception`** — a plain
`\Exception` subclass with no structured position; the line/column is baked into
the message text (`on line N at column N`, formatted by `TokenStream::error`).
Sources: lexer unmatched input, parser errors via `TokenStream::error`, and string
escape errors — `StringNode::parse` throws those position-less, but
`Parser::parseValue` catches and re-throws them through `TokenStream::error`, which
adds the position. One exception to the rule: the lexer's invalid-UTF-8 error
(`Invalid UTF-8 sequence.`) is thrown directly and carries no position at all.
Encoding is more lenient: an **invalid UTF-8 string is not an exception** — the
encoder emits an `E_USER_WARNING` and substitutes U+FFFD. (A dedicated
`SyntaxErrorException` subclass is a proposed idea, not yet in code — do not assume
it exists.)

## Traverser rewrites through by-reference iteration

`Traverser::traverse($root, $enter, $leave)` is the AST-rewriting tool: a callback
may return a replacement `Node`, `Traverser::DontTraverseChildren`, or
`Traverser::StopTraversal`. The mechanism is non-obvious: `Node::getIterator()` is
a **by-reference generator** (`&getIterator()`), each node class yields its
children by reference, and the Traverser assigns replacements straight into the
parent via `foreach ($node as &$subnode)`. Consequences:

- A new node type must implement a by-reference `getIterator()` yielding every
  child, or traversal silently fails to rewrite its children.
- `ArrayNode::getIterator()` runs a cleanup after the loop (`array_filter` +
  `array_values`), so code iterating by reference can delete an item by setting it
  to null — the removal materializes only once the loop completes.

## Navigation map

| Concern | Where |
|---|---|
| Tokenization, one-regex grammar | `Lexer::tokenize`, `Lexer::Patterns` |
| Full-stream + peek/consume/indentation | `TokenStream` (`isNext`, `consume`, `getIndentation`) |
| Block/inline parse, dash backtrack | `Parser::parseBlock`, `parseBraces`, `parseValue` |
| Token index ↔ source position | `Parser::injectPos`, `Node` position fields |
| Multiline string trap, delimiter choice | `StringNode::parse`, `toString` |
| Value → node, bare-vs-quoted | `Encoder::valueToNode`, `Lexer::requiresDelimiters` |
| AST rewriting, by-ref child iteration | `Traverser`, `Node::getIterator` overrides |
