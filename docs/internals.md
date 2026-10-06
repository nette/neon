# Neon internals

How `nette/neon` 4.0 works, for agents editing it. The norm is the language-neutral specification
in `tests/neon-spec` (`github.com/nette/neon-specification` at the commit of `tests/neon-spec.lock`): grammar, token and node model,
value mapping, editing rules, error messages and the linter contract, together with the conformance
corpus every implementation runs. This file describes how the PHP package realizes it and where it
is easy to go wrong; it does not restate the spec.

## The pipeline

```
text ── Lexer ──> tokens with trivia ── TokenStream + Parser ──> DocumentNode
DocumentNode ── Evaluator ──> PHP value                     (Neon::decode)
DocumentNode ── print() ──> the same text                   (lossless)
PHP value ── Encoder ──> text                               (Neon::encode)
edit methods of nodes ── Editor, Builder, Layout ──> the tree changed in place
```

`Neon::decode()` is `parse()` followed by `Evaluator::evaluate()`; there is no second, faster parser
with other semantics, and there must not be one. `Neon::parse()` returns the tree; only the facade,
`Entity`, `Exception`, `Position`, `Token`, `Trivia`, `Node` and `Nodes\*`, `Evaluator`, `Traverser`,
`Printer` and the enums are public. `Lexer`, `TokenStream`, `Parser`, `Scalars`, `Encoder`, `Builder`,
`Layout`, `Editor`, `Journal` and `Linter` are `@internal`.

## The lossless tree

- **Every byte of the input is in a token.** Whitespace, line endings, comments and the byte order
  mark are `Trivia` attached to tokens. The trailing trivia of a token run up to and including the
  first line ending after it; everything else before the next token is its leading trivia. Trivia
  after the last token belong to `EndOfFile`, which every document ends with. Printing is the
  concatenation of all tokens with their trivia, and `print(parse(s)) === s` holds for every input
  the parser accepts.
- **Canonical form of trivia** is what keeps that true after edits, and `Token::setLeadingTrivia()`
  and `setTrailingTrivia()` enforce it, also against the neighbours: leading trivia only after a line
  ending, a line ending last of the trailing trivia, a comment always followed by a line ending, no
  two whitespace trivia side by side, a BOM only at the start, no CR at the end of a trivia before an
  LF (the two would read as one line ending and change the meaning). A lone CR is whitespace, not a
  line ending; `Position` counts only LF.
- **Kinds are int constants** of `Token` and `Trivia` (`Token::Literal`, `Trivia::Comment`), not
  enums: a kind is only stored and compared, and an int keeps `Token::is(kind|text)` unambiguous.
  Enums exist only where a kind is passed as a type: `CommentPolicy`, `TraverseAction`, `Place`.
- **Trivia are immutable and shared.** The lexer reuses one instance for equal texts across tokens,
  which removed most of the cost of the cycle collector on large inputs. Within one list an instance
  appears at most once, because `Token::replaceTrivia()` finds its target by identity. Never mutate
  a trivia; `withText()` returns a copy.
- **Nodes and tokens know their parent.** A child has exactly one parent. Constructors of nodes are
  `@internal` and check nothing: only the parser, `Editor` and `Builder` call them, with tokens and
  children that are valid and free by construction. A node from outside comes from `parse()`,
  `clone` or `detach()`/`remove()`, so it has the shape the parser or an edit gave it, and the edit
  methods guard everything that depends on where it is put. The way to a new node is
  `Neon::parse(...)->value->detach()` or `setValue()` with a PHP value. `Slots` is ordering
  metadata for `getChildren()`, the walk, the dump and the clone; nothing is adopted or validated by
  it.
- **Positions are computed, not stored.** `Node::getPosition()` and `Token::getPosition()` walk from
  the root, so they are O(n) per call; `Printer::locateAll()` gives all of them in one walk.
  `Token::$origin` is where the token stood in the source it was read from and does not follow edits.
- `clone $node` is a deep copy without a parent: new nodes and tokens, shared trivia and positions.
  Serialization of the tree is refused; what persists is NEON text.

## Writing to the tree

Properties are read directly and written only through methods that guard shape, ownership and
meaning: token properties are `public private(set)`, slots of nodes `public protected(set)`.

- **Raw writes** are a small set of `@internal` methods on the classes written to:
  `Node::writeParent()`, `Node::writeSlot()`, `Token::writeParent()`, `writeLeadingTrivia()`,
  `writeTrailingTrivia()`. They validate nothing. Only the constructors of nodes, the clone, `Builder`
  and `Editor` call them; PHPStan reports any other use. PHP offers no real protection here, so the
  point is that the public API never tempts anyone into an unchecked write.
- **Every raw write records its undo** in the journal while `Journal::atomic()` runs (outside it,
  `Journal::isRecording()` is false and recording costs nothing, which is what keeps parsing cheap).
  A new raw write that bypasses these methods breaks rollback; the snapshot tests catch it.
- **Every public edit runs in `Journal::atomic()`.** Any `Throwable`, `TypeError` and `Error`
  included, undoes the recorded writes in reverse order, of the target and of a donor tree alike,
  and the exception goes on unchanged. A nested call is a savepoint that undoes only its own part.
  Undo goes through the same raw writes without recording, so identities, parents, trivia and texts
  come back exactly.
- **Consequence for the editor:** a refusal does not have to be found before the first write. Checks
  that are properties of the result (an implicit key beyond 2^63-1 after an insert, the depth limit,
  the repairs of the grammar below) run on the result, and a failure rolls everything back.
- **Outside the guarantee** is what the library does not own. Hence the node methods read the
  iterators of a value (`Builder::materialize()`) **before** the journal opens, so what user code
  does there is not undone, and the value is built into nodes before the tree is touched. Only an
  iterator held by a property of an object is read later, by the encoder, as the encoder alone
  decides which objects it takes.

## Lexer

- One PCRE expression realizes the scanner of the spec. `preg_match_all` with `PREG_SET_ORDER`,
  alternatives tagged by `(*MARK)` with the name of the kind; the order of the alternatives is
  explained at the expression. The last alternative catches any unexpected character, so the matches
  always cover the whole input; a gap is a `LogicException`, never a silent end.
- **A PCRE failure is not a syntax error.** `preg_last_error()` is checked and a failure is a
  `\RuntimeException`, which the linter reports as a tool failure (exit 2). PCRE limits are never
  raised for the user.
- Invalid UTF-8 is detected first and reported at the first invalid byte, computed from the valid
  prefix.
- `Lexer::isLiteral()` is how the encoder and the editor decide that a string needs quotes: "can be
  written bare" is defined by the scanner itself, not by a second regex.
- Multiline strings keep the 3.x indentation rule (the first non-empty line sets it). The lexer
  closes a `'''` string at the first later line holding only spaces and the triple; the encoder and
  the editor avoid the texts that would read back differently (`"""` when a line would start with
  `'''`, see `Editor::hasFragileString()`).

## Parser

- `TokenStream` presents line endings as a **virtual `Newline`** before a token whose leading trivia
  hold one; it is consumed once and never enters the tree. `getIndentation()` reads the whitespace
  right after the line ending.
- `Parser` is the algorithm of 3.x over that stream. A block opened on the line of a dash has its
  indentation decided lazily, by the line where it is first compared (a tab if that line starts with
  one, two spaces otherwise). 3.x tried the tab and then the spaces, which is exponential in nested
  dashes; do not reintroduce backtracking.
- A key followed by a dash at the same indentation opens a dash subblock of that key (`onlyBullets`).
  The editor has to keep this from happening by accident (`Editor::guardDashSubblocks()`).
- Duplicate keys are found while parsing, explicit and implicit alike, against the canonical keys of
  `Scalars`; implicit numbering follows the greatest integer key so far.
- `Parser::MaxDepth` (500 levels of arrays) is a controlled error in the parser, the encoder and the
  editor alike; everything below it may recurse.
- `verify()` checks after parsing that every token of the stream is in the tree once, in order.

## Values: `Scalars` and `Evaluator`

- `Scalars` is the one authority on what literals, strings and keys mean: classification of
  literals, escapes, canonical keys, integers beyond the native range (a string of digits, without
  bcmath), dates (`DateTimeImmutable`; an invalid date such as `2024-02-30` is a string). The parser
  calls it for validity, the evaluator for values. Nothing is cached in nodes; `toValue()` computes
  afresh every time and returns fresh `Entity` objects.
- Keys are 64-bit whatever the platform (`Scalars::IntMax`).
- `Evaluator` is the extension point (the DI container uses it). Subclasses override
  `evaluateLiteral()`, `evaluateString()`, `evaluateEntity()`, `evaluateChain()` and
  `evaluateItem()`; the `Place` tells a value from a key, a head of an entity and a link of a chain,
  and `evaluateChain()` gets the whole chain, so a subclass can build one value from all its links.
  `evaluateArray()` is final and fixes the order: syntactic keys and implicit numbering on the
  original keys, then the projection of keys, then of values, then the check that two different keys of the document did not project to
  one (`Exception` "Duplicated key", with the position of the second), then insertion. A projected
  key that is not int or string is a `LogicException`. The evaluator never changes the tree.

## Encoder

- Every array, object and iterator is **materialized once** into a list of entries with validated
  keys, and the text is written from it; an iterator is never read twice. Objects are maps of their
  public properties; `Node` and `Token` are refused (a node enters a document by editing).
- Failures carry the path to the value: invalid UTF-8 (an error, not U+FFFD), a key collision after
  canonicalization, a cycle of objects or entities by identity, a cycle of arrays through references
  via the depth limit, an unsupported type.
- Floats are the shortest text that reads back as the same double (`sprintf('%.{n}e')` with growing
  precision, digits laid out by hand), independent of `precision`, `serialize_precision` and the
  locale; `-0.0` stays. Dates are written without the fraction of a second, as in 3.x, so a
  round trip drops it.
- `Builder` makes nodes for the editor from the encoder's text, parsed, so new values look like
  encoded ones and are valid by construction.

## Editing

The node methods (`DocumentNode::setValue()`, `addItem()`, `removeItem()`, `setKey()`,
`ArrayItemNode::setValue()`, `setKey()`, `remove()`, `ArrayNode::addItem()`, `insert()`,
`removeItem()`, `ValueNode::replaceWith()`, `detach()`) are thin; the work is in `Editor`, with
`Layout` deciding the shape of new text. The contract is the mutations chapter of the spec and the
`mutations/` corpus; the traps of the implementation:

- **Writing an equal value is a textual no-op.** Equality is that of PHP values (`0x10` and 16,
  `yes` and true, a date and its text); the raw text is compared first, then the built node with
  `isSameNode()`, where an empty map differs from an empty list. A changed scalar keeps the style of
  the old one where it can (base and letter case of an integer, the word family of a boolean, the
  quotes of a string). An intentional change of syntax is `setValue()` with a node. Equality and
  paths use the default `Evaluator`; a subclass of it does not change what the editor sees.
- **Edits must not change other keys.** Adding or removing a keyless item in a list could renumber
  its neighbours, and a key beyond the implicit counter could change the implicit key of the next
  item; `keepKeys()` writes out the keys that would otherwise change.
- **Repairs of the grammar** after a structural change: a key without a value before a dash gets an
  explicit `null`, a dash after a dash subblock gets its key written out
  (`guardDashSubblocks()`), whitespace on the last line is stripped (`guardEnd()`).
- **Comment ownership:** an item owns the comment on its line and the block of comment lines right
  above it with no blank line between. A comment block separated by a blank line is a preamble (a
  file header, a section heading) and stays under every `CommentPolicy`. `Drop` (the default) drops
  only owned comments; `MoveToNextToken` keeps them above the next item. Moving an item with
  `insert()` carries its owned comments.
- **Layout** of new text comes from the neighbourhood: the indentation unit from the siblings, then
  the ancestors, then the whole document; the indentation of a block opened on the line of a dash
  is that of the first item, after the dash; the line ending of the nearest preceding token.
- Removing the last item leaves `[]` or `{}`, unless the item is marked `nullable`; then the value
  becomes null.

## Traverser

`Traverser::traverse($node, $enter, $leave)` walks nodes, not tokens. A visitor returns nothing, a
`ValueNode` to replace a value node, or a `TraverseAction` (`SkipChildren`, `Stop`, `Remove` for an
item); anything else is a `LogicException`, never silently ignored. The trap of that rule: an arrow
function whose expression happens to be the visited item (`fn($n) => $all[] = $n`) returns a node and
fails on a node that cannot be replaced.

- The children of a node are taken when it is entered, so nodes inserted around the visited one
  later are not visited.
- The walk keeps the path of entered nodes with the parents they were entered under; a node that
  no longer stands there (a visitor removed it or an ancestor, directly) is neither walked into nor
  left, and a child taken out meanwhile is skipped. A visitor may therefore edit siblings and
  ancestors directly.
- A replacement returned by `enter` is walked into, so `enter` that wraps a node into something
  holding it again recurses until the depth limit; wrapping belongs in `leave`.
- `Stop` ends the walk at once, without `leave` of the nodes entered: `leave` can rewrite, and
  rewriting after `Stop` would contradict it.
- The walk is not one atomic edit; each edit of a visitor is, and those before a failure stay.

## Linter

`bin/neon-lint` is a thin wrapper of `Linter::main()`. It lints, it does not print trees: options
`--check` (printing the tree gives the file back), `--json`, `--quiet`, `--verbose`; exit 0 when all
files are valid, 1 for findings, 2 for a failure of the tool. The contract is shared with the other
implementations.

## Tests

- `corpus.phpt` runs the conformance corpus through a strict loader (anything it does not know is a
  failure); `NEON_CORPUS` points it at another checkout.
- `mutations.phpt` and `editing.phpt` check edits; every refusal is checked by a snapshot of all
  tokens, parents, texts and the identity of the trivia arrays, of the target and the donor
  (`tests/helpers/guarantees.php`), and every result by `checkTree()` (reparse gives the same dump,
  parents agree, trivia canonical).
- `mutation-fuzz.phpt` applies random edits to corpus documents and compares the value against an
  independent model; `FUZZ_SEEDS` and `FUZZ_SEED` widen or pin it. It asserts minimal shares of
  applied and refused edits, so it cannot pass empty.
- `Decoder.*.phpt`, `Encoder.phpt` and `Neon.decodeFile.phpt` keep the behaviour of 3.x;
  `Encoder.values.phpt` holds the new encoder cases (a file `encoder.phpt` would collide with
  `Encoder.phpt` on case-insensitive file systems).
- The dump used by the corpus and the tests is `tests/helpers/dumper.php`, not part of the package.

## Navigation map

| Concern | Where |
|---|---|
| Scanner, trivia attachment and sharing | `Lexer::Pattern`, `Lexer::tokenize()` |
| Virtual Newline, indentation | `TokenStream` |
| Blocks, dash blocks, keys, depth | `Parser::parseBlockItems()`, `parseDashBlock()`, `addKey()`, `createItem()` |
| Meaning of literals, strings, keys | `Scalars` |
| Value projection, extension point | `Evaluator` |
| Writing values as text | `Encoder` |
| Canonical trivia, checks against neighbours | `Token::setLeadingTrivia()`, `setTrailingTrivia()` |
| Raw writes and rollback | `Node::write*()`, `Token::write*()`, `Journal` |
| Edits, no-op, style, comments, keys | `Editor` |
| New nodes, indentation, line endings | `Builder`, `Layout` |
| Positions of many tokens | `Printer::locateAll()` |
