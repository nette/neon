# The Evaluator: your own projection of the tree

`Neon::decode()` is the tree projected onto PHP values by `Evaluator`. A program that gives some
values a meaning of its own, parameters, constants, references, does not rewrite the tree: it
subclasses `Evaluator` and changes how literals, strings, entities or items become values. The DI
container of Nette reads its configuration this way.

**You will learn:**

- which hooks there are, and what the `Place` of a value tells a hook
- how a hook reports an error at the position of the node
- that evaluating changes nothing in the tree

```shell
php examples/evaluator/evaluator.php
```

## Parameters and constants (evaluator.php)

```
array (
  'parameters' => 
  array (
    'tempDir' => '/var/www/temp',
  ),
  'errorLevel' => 30719,
  '/var/www/log' => 'enabled',
  'plain' => 'E_NOTHING',
)
true
Unknown parameter %logDir% on line 2 at column 4
```

The hooks are `evaluateLiteral()`, `evaluateString()`, `evaluateEntity()` and `evaluateItem()`.
Each literal and string comes with its `Place`: a value, a key, the head of an entity, or an entity
as a link of a chain. Here a constant is resolved only as a value, so `E_ALL` becomes its number
while a key or an entity name spelled in capitals would stay text, and `E_NOTHING`, which is no
constant, stays a string. A parameter is expanded everywhere, in the key `"%appDir%/log"` too.

The keys of an array and their order stay those of the document; if two different keys of the file
became one key after the projection, `evaluate()` would fail at the second one instead of losing a
value. A hook knows where it is: `$node->getPosition()` gives the line and the column for its error.
The document prints the same text after the evaluation as before it.
