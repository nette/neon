# Edge cases: the corners real files have

NEON has a few rules that surprise a newcomer, and a tool that reads and writes configuration
files meets all of them sooner or later. They are the rules every Nette application has read
since 2011, and every implementation of NEON keeps them, so a file means the same everywhere.

**You will learn:**

- how a byte order mark, CRLF and a missing final line ending are kept
- the dash subblock, a block opened on the line of a dash, and a value on the next line
- how the indentation of a multiline string is found
- which words are not booleans, how implicit keys count, and what a date that is no date is

```shell
php examples/edge-cases/edges.php
```

## The rules (edges.php)

```
BOM and CRLF               {"a":1,"b":2}
dash subblock              {"list":["a","b"],"next":1}
block on a dash, tab       [{"a":1,"b":2}]
block on a dash, 2 spaces  [{"a":1,"b":2}]
block on a dash, 4 spaces  Bad indentation on line 2 at column 5
value on the next line     {"a":"x"}
multiline indentation      {"a":"deeper\n\tshallower"}
words that are not bool    ["on","off","tRuE"]
implicit keys              {"5":"a","6":"b"}
invalid date               "2024-02-30"

add to CRLF without EOL   "a: 1\r\nb: 2\r\nc: 3"
encode leading spaces     "\"\"\"\n\t\\u0020\\u0020indented first line\n\tsecond\n\"\"\"" true
```

A **dash subblock** is a list written at the same indentation as its key; it ends at the first
item with a key. A **block opened on the line of a dash** (`- a: 1`) continues on the next line
indented by a tab or by two spaces, nothing else, which is why four spaces are an error. The
**indentation of a multiline string** is that of its first line; a line indented less keeps its
whitespace. `on`, `off` and odd spellings like `tRuE` are strings, though the encoder quotes them.
An item without a key gets **one more than the greatest integer key** before it. A literal shaped
like a date whose components make no date, the 30th of February, is a string.

Edits keep the line endings of the file and its missing final line ending. And the encoder writes
a string whose first line starts with spaces in `"""` with escapes, because in `'''` those spaces
would be read as indentation.
