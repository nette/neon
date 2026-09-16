# Upgrading nette/neon

## 3.4.7

- **Changed** `Nette\Neon\Neon::encode()` replaces an invalid UTF-8 sequence in a string with U+FFFD and triggers `E_USER_WARNING`; previously the bytes were written out unchanged (cc96bf52)

## 3.4.6

- **Changed** `Nette\Neon\Neon::encode()` indents a multiline block string with the string given in `$indentation`; previously a tab was used regardless of the argument (cb88b814)
- **Changed** `Nette\Neon\Exception` thrown while parsing reads `... on line X at column Y`; previously `... on line X, column Y.`; affects code matching on the message text (36e3f4f8)

## 3.4.3

- **Changed** `Nette\Neon\Neon::decode()` returns an integer literal too large for a native `int` as a `string`; previously it was silently rounded to a `float` (b2b28ae6)
- **Changed** `Nette\Neon\Neon::decode()` throws `Nette\Neon\Exception` for a hexadecimal, octal or binary literal of 16 or more digits when `ext-bcmath` is not loaded; previously such a number was returned as an imprecise `float` (b2b28ae6)

## 3.4.1

- **Changed** `Nette\Neon\Neon::encode()` throws `Nette\Neon\Exception` for `INF` and `NAN`; previously they were written out as invalid NEON (5715e170)

## 3.4.0

- **Removed** the `\xHH` string escape sequence (deprecated since 3.2.0): write `\uXXXX` instead; using it throws `Nette\Neon\Exception` (9118b540)
- **Renamed** `$attrs` → `$attributes` of `Nette\Neon\Entity::__construct()`; affects calls using named arguments; the old name was removed (ad41785b)
- **Deprecated** `Nette\Neon\Neon::BLOCK`: pass `true` as `$blockMode` of `Nette\Neon\Neon::encode()`; the old name is silently deprecated (682ad26c)
- **Deprecated** `Nette\Neon\Neon::CHAIN`: use `Nette\Neon\Neon::Chain` (alias since 3.3.3); the old name is silently deprecated (682ad26c)
- **Signature** `Nette\Neon\Entity::$attributes`: typed `array` with the default `[]`; previously untyped, so assigning another type now throws a `TypeError` (3914f3d5, ad41785b)
- **Changed** the NEON literals `on` and `off` (deprecated since 3.1.0) are decoded as the plain strings `'on'` and `'off'`; previously as `true` and `false` with an `E_USER_DEPRECATED` notice; write `true`/`yes` and `false`/`no` (9118b540)

## 3.3.4

- **Changed** `Nette\Neon\Neon::decodeFile()` throws `Nette\Neon\Exception` with the message `Unable to read file '...'` whenever the file cannot be read; previously it threw `File '...' does not exist.` for a missing file and read an existing but unreadable file as an empty document; affects code matching on the message text (0452960a)
- **Changed** `Nette\Neon\Neon::encode()` writes a string containing a control character (`\x00`-`\x08`, `\x0B`-`\x1F`) in the JSON-quoted form; previously such a string was single-quoted, which produced invalid NEON; also in 3.4.4 (bb88bf3a)

## 3.3.3

- **Renamed** `Nette\Neon\Neon::CHAIN` → `Nette\Neon\Neon::Chain`; the old name still works as an alias (d899ece6)
- **Changed** `Nette\Neon\Neon::encode()` writes a single-line string in single quotes with `''` for an embedded quote, and a multiline string as a `'''` block indented with a tab (a `"""` block when the value itself contains `'''`); previously a single-line string was double-quoted with JSON escaping and a multiline one always used `"""` (22e384da)

## 3.3.1

- **Signature** `Nette\Neon\Neon::encode()`: the second parameter is now `bool $blockMode` instead of the `int $flags` bitmask, and `Nette\Neon\Neon::BLOCK` is `true` instead of `1`; passing the constant still selects block mode, passing `1` under `strict_types` throws a `TypeError` (976140f1)

## 3.2.2

- **Renamed** `$var` → `$value` of `Nette\Neon\Neon::encode()`; affects calls using named arguments; the old name was removed (a5c675f3)
- **Deprecated** `Nette\Neon\Encoder`: use `Nette\Neon\Neon::encode()`; the class is marked `@internal`; the old name is silently deprecated (a5c675f3)

## 3.2.0

- **Deprecated** the `\xAA` string escape sequence: use `ꪪ`, which encodes a code point instead of a raw byte; using the old name triggers `E_USER_DEPRECATED` (d5f85dec)
- **Changed** `Nette\Neon\Neon::decode()` throws `Nette\Neon\Exception` for input containing an invalid UTF-8 sequence; previously such input was tokenized in byte mode without notice (5b1ff4bb)
- **Changed** `Nette\Neon\Neon::encode()` writes a string containing a newline as a `"""` block; previously as a single-line double-quoted string with `\n` escapes (72dd8031)

## 3.1.2

- **Changed** `Nette\Neon\Neon::decode()` keeps a key matching the date-time pattern as a string; previously such a key was converted to a `DateTimeImmutable` (6a83f3ef)
- **Changed** `Nette\Neon\Neon::encode()` quotes a string starting with a digit, or with `+`, `-` or `.` followed by a digit; previously only a fully numeric string or one starting with four digits was quoted, so the rest did not survive a round trip (3c3dcbc6)

## 3.1.1

- **Changed** `Nette\Neon\Neon::encode()` throws `Nette\Neon\Exception` for a value containing an invalid UTF-8 sequence; previously the failed encoding was written out as `false`, an empty string (35f7229f)
- **Changed** a single-quoted NEON string reads `''` as one literal quote; previously the first `'` ended the string (53703908)

## 3.1.0

- **Removed** `src/neon.php`, the bootstrap file requiring the individual classes: load the package through the Composer autoloader (9cd8bcb0)
- **Deprecated** the NEON literals `on` and `off`: write `true`/`yes` and `false`/`no`; using the old name triggers `E_USER_DEPRECATED` (ce8555b8)
- **Changed** an unquoted NEON literal may no longer start with `-` or `:` followed by `=`, `[`, `]`, `{`, `}` or `(`; quote such a value (00f31ef4)
- **Changed** an unquoted NEON literal may no longer start with `-` or `:` immediately after a quote character, for JSON compatibility; quote such a value (e4e30bd0)
