# Parsing: values, the tree, and errors that say where

Two ways in. `Neon::decode()` gives you the value, the way `json_decode()` does. `Neon::parse()`
gives you the syntax tree, and the tree prints back as exactly the text it came from.

**You will learn:**

- what PHP values NEON maps to: `int`, `float`, strings for integers beyond `PHP_INT_MAX`, `DateTimeImmutable`, `Entity`
- what an error tells you, and where
- that printing a parsed document gives back every byte of it

```shell
php examples/parsing/decode.php
php examples/parsing/roundtrip.php
```

## Values (decode.php)

```
debug:   true
port:    8080
started: DateTimeImmutable 2016-06-03 19:00:00 +02:00
hosts:   ["alpha","beta"]
service: Nette\Neon\Entity Mailer {"0":"@smtp","timeout":30}
big:     string 12345678901234567890

Bad indentation on line 2 at column 3
Bad indentation | line 2, column 3
```

`yes` is `true` and `0x1F90` is `8080`. A date is a `DateTimeImmutable`; a date that is not one,
like `2024-02-30`, stays a string. `Mailer(@smtp, timeout: 30)` is an `Entity`, the value and the
attributes, which is how the DI container of Nette writes a service. An integer too big for an
`int` is a string of its digits, not a rounded float.

An error is a `Nette\Neon\Exception`: the message ends with the position, `getDescription()` is the
message without it, and `$position` holds the line and the column, counted in code points.

## The tree prints back as it was (roundtrip.php)

```
true {"key":"value"}
true {"tabs":["a"],"spaces":[{"b":1,"c":2}]}
true {"no final line ending":["a","b","c"]}
true {"multiline":"keeps\n\tits indentation"}
```

A byte order mark, CRLF, tabs in one section and spaces in another, a comment in an odd place,
spaces around commas, no line ending at the end: `Neon::parse($text)->print() === $text` for every
input NEON accepts. That is what makes the editing in the next chapters possible: nothing that is
in the file is lost on the way through the tree.
