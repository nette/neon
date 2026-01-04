# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**Nette NEON** is a parser/encoder library for the NEON format (Nette Object Notation), a human-readable structured data format similar to YAML but with unique features like entities. It's used primarily for configuration files in the Nette Framework.

- **Repository**: https://github.com/nette/neon
- **Homepage**: https://neon.nette.org
- **PHP Requirements**: 8.2 - 8.5
- **Current Version**: 3.5-dev

## Essential Commands

### Testing
```bash
# Run all tests
composer run tester

# Run specific test file
vendor/bin/tester tests/Neon/Decoder.phpt -s -C

# Run tests in specific directory
vendor/bin/tester tests/Neon/ -s -C
```

### Static Analysis
```bash
# Run PHPStan (level 6)
composer run phpstan
```

### Validation
```bash
# Lint NEON files
vendor/bin/neon-lint <path>
```

## Architecture

### Parser Pipeline

The library follows a classic lexer → parser → AST → value pipeline:

1. **Lexer** (`src/Neon/Lexer.php`): Tokenizes NEON input using regex patterns
   - Produces tokens with type, text, and position information
   - Token types: `String`, `Literal`, `Char`, `Comment`, `Newline`, `Whitespace`, `End`

2. **TokenStream** (`src/Neon/TokenStream.php`): Manages token buffer
   - Handles whitespace/comment skipping
   - Provides token consumption and seeking

3. **Parser** (`src/Neon/Parser.php`): Builds Abstract Syntax Tree
   - Converts token stream to Node tree
   - Handles block (indentation-based) and inline (bracket-based) notation
   - Detects mixed tabs/spaces errors

4. **Node Hierarchy** (`src/Neon/Node/`): AST representation
   - All nodes extend abstract `Node` class with `toValue()` and `toString()` methods
   - Node types:
     - `LiteralNode` - numbers, booleans, null, dates
     - `StringNode` - quoted and unquoted strings
     - `EntityNode` - NEON entities like `Column(type: int)`
     - `EntityChainNode` - chained entities
     - `BlockArrayNode` / `InlineArrayNode` - arrays in different notations
     - `ArrayItemNode` - individual array items with optional key
   - All nodes track position (`start`, `end` as `Position` objects)

5. **Decoder** (`src/Neon/Decoder.php`): Public API for parsing
   - `parseToNode()` - returns AST
   - `decode()` - returns PHP value (calls `toValue()` on root node)

6. **Encoder** (`src/Neon/Encoder.php`): Converts PHP values to NEON
   - `valueToNode()` - creates Node tree from PHP value
   - `encode()` - returns NEON string (calls `toString()` on nodes)
   - Supports block and inline rendering modes

7. **Traverser** (`src/Neon/Traverser.php`): AST manipulation utility
   - Visitor pattern for node processing
   - Supports `enter` and `leave` callbacks
   - Return constants: `RemoveNode`, `StopTraversal`, `DontTraverseChildren`

### Key Design Patterns

- **Position Tracking**: All tokens and nodes include `Position` (line, column, offset) for precise error reporting
- **Readonly Classes**: `Token` and `Position` are readonly for immutability
- **Final by Default**: All concrete classes are `final` unless designed for extension
- **Entity Pattern**: `Entity` class extends `stdClass` with `value` and `attributes` properties
- **Type Safety**: All parameters, properties, and return types are strictly typed

## Code Style

### PHP Standards
- Every file must start with `declare(strict_types=1)`
- Use tabs for indentation
- Use single quotes for strings (except when escaping needed)
- PHP 8.2+ features are standard (readonly, union types, named parameters)

### Naming Conventions
- Classes: PascalCase (e.g., `BlockArrayNode`)
- Methods: camelCase (e.g., `toValue()`, `toString()`)
- Constants: PascalCase (e.g., `Token::String`, `Traverser::RemoveNode`)
- Namespace: `Nette\Neon`

### Documentation
- Minimalist phpDoc - only document non-obvious information
- Don't duplicate type information already in signatures
- Use `@internal` for classes not meant for public use
- Focus on explaining "why" and edge cases, not "what" (which is in the signature)

### Return Type Formatting
```php
public function example(
	string $param,
	array $options,
): ReturnType
{
	// method body on new line after opening brace
}
```

## Testing Patterns

Tests use **Nette Tester** with `.phpt` extension:

```php
<?php

declare(strict_types=1);

use Tester\Assert;
use Nette\Neon\Neon;

require __DIR__ . '/../bootstrap.php';

test('description of what is being tested', function () {
	$result = Neon::decode('hello: world');
	Assert::same(['hello' => 'world'], $result);
});

test('handles edge case properly', function () {
	Assert::exception(
		fn() => Neon::decode('invalid [[['),
		Nette\Neon\Exception::class,
		'Unexpected %a%',  // %a% = any text wildcard
	);
});
```

**Key Points**:
- Use `test()` function for each test case (provided by bootstrap)
- First parameter is clear description (no comments needed)
- Use `Assert::same()` for exact comparisons
- Use `Assert::exception()` for exception testing
- Message patterns support `%a%` (any text) placeholders

## NEON Syntax Rules

Understanding these rules is critical when working with the parser:

### Block vs Inline Notation
- **Block notation**: Items on separate lines with consistent indentation
- **Inline notation**: Enclosed in brackets `[]` or braces `{}`, indentation irrelevant
- **Critical limitation**: Block notation CANNOT be used inside inline notation

```neon
# This works
pets: [Cat, Dog]

# This does NOT work
item: [
	pets:
	 - Cat     # INVALID!
	 - Dog
]
```

### Indentation Rules
- Both tabs and spaces allowed for indentation
- **Cannot mix** tabs and spaces (parser will report error)
- Indentation level determines structure hierarchy
- Space after `:` in key-value pairs is **required**

### Mappings (Associative Arrays)
- Format: `key: value` (space after `:` required)
- Alternative: `key=value` (both block and inline)
- Inline: `{key: value, key2: value2}` or multiline with `{}`

### Sequences (Indexed Arrays)
- Block: Lines starting with `- ` (hyphen + space)
- Inline: `[item1, item2]`
- Hyphens cannot be used in inline notation

### String Quoting
Must quote strings containing: `# " ' , : = - [ ] { } ( )`
- Single quotes: Escape quotes by doubling `''`
- Double quotes: JSON escape sequences + `\_` for non-breaking space
- Unquoted: Simple strings without special characters

### Multiline Strings
```neon
'''
	content here
	'''
```
- Triple quotes on separate lines
- Indentation of first line ignored for all lines
- Use `"""` for escape sequences

### Comments
- Start with `#`
- All characters to the right are ignored
```neon
# Full line comment
key: value  # Inline comment
```

### Mixed Arrays
PHP uses same structure for mappings and sequences, so they can be merged:
```neon
- Cat                          # indexed item 0
street: 742 Evergreen Terrace  # key 'street'
- Goldfish                     # indexed item 1
```

Becomes:
```php
['Cat', 'street' => '742 Evergreen Terrace', 'Goldfish']
```

## Common Patterns

### Parsing NEON
```php
// To PHP value
$value = Nette\Neon\Neon::decode($neonString);

// From file (removes BOM automatically)
$value = Nette\Neon\Neon::decodeFile('config.neon');

// To AST (for manipulation)
$decoder = new Nette\Neon\Decoder;
$node = $decoder->parseToNode($neonString);
```

### Error Handling
All methods throw `Nette\Neon\Exception` on error with position information:

```php
try {
	$value = Neon::decode($neonString);
} catch (Nette\Neon\Exception $e) {
	// Exception includes line/column position
	echo $e->getMessage();
}
```

### Encoding to NEON
```php
// Inline mode (single line)
$neon = Nette\Neon\Neon::encode($value);

// Block mode (multiline)
$neon = Nette\Neon\Neon::encode($value, blockMode: true);

// Custom indentation (default is tab)
$neon = Nette\Neon\Neon::encode($value, blockMode: true, indentation: '  ');
```

### AST Traversal
```php
$traverser = new Nette\Neon\Traverser;
$newNode = $traverser->traverse($node,
	enter: function ($node) {
		// Process node before children
		if ($node instanceof StringNode) {
			return new StringNode($node->value . ' modified', $node->start, $node->end);
		}
	},
	leave: function ($node) {
		// Process node after children
	}
);
```

## Special Features

### JSON Compatibility
**JSON is a subset of NEON** - any valid JSON can be parsed as NEON:
```php
$value = Neon::decode('{"key": "value"}'); // Valid NEON
```

### Entities
NEON entities are function-like structures parsed into `Entity` objects:

```neon
Column(type: int, nulls: yes)
```

Becomes:
```php
new Nette\Neon\Entity('Column', ['type' => 'int', 'nulls' => true])
```

Chained entities use `Entity::Chain`:
```neon
Column(type: int) Field(id: 1)
```

Inside entity parentheses, inline notation rules apply (multiline allowed, commas optional):
```neon
Column(
	type: int
	nulls: yes
)
```

### Literals (Booleans and Nulls)

**Booleans** - case insensitive variants:
- `true` / `TRUE` / `True`
- `false` / `FALSE` / `False`
- `yes` / `YES` / `Yes`
- `no` / `NO` / `No`

**Nulls** - case insensitive variants:
- `null` / `NULL` / `Null`
- Empty value (just `key:` without value)

### Date Parsing
NEON automatically converts date strings to `DateTimeImmutable`:
```neon
- 2016-06-03                  # date
- 2016-06-03 19:00:00         # date & time
- 2016-06-03 19:00:00.1234    # date & microtime
- 2016-06-03 19:00:00 +0200   # date & time & timezone
- 2016-06-03 19:00:00 +02:00  # date & time & timezone
```

### Number Formats
Supports binary, octal, hexadecimal, and scientific notation:
```neon
- 12         # integer
- 12.3       # float
- +1.2e-34   # scientific notation
- 0b11010    # binary
- 0o666      # octal
- 0x7A       # hexadecimal
```

## Important Notes

- **This is a parser library** - focus on correct format handling, not business logic
- **Position tracking is critical** - all parsing includes line/column info for error messages
- **Minimal dependencies** - only requires `ext-json`
- **Strict types everywhere** - proper type declarations are essential
- **Final by default** - classes are `final` unless designed for extension
- **Format preservation** - upcoming feature; be careful with changes that affect formatting
