<?php

declare(strict_types=1);

use Nette\Neon;
use Nette\Neon\Node;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';

$visitor = fn(Node $node) => clone $node;

$decoder = new Neon\Decoder;
$node = $decoder->parseToNode('
a: foo(1, 2, 3)
');

$traverser = new Neon\Traverser;
$newNode = $traverser->traverse($node, $visitor);

Assert::equal($node, $newNode);
Assert::notSame($node, $newNode);

Assert::equal($node->items[0], $newNode->items[0]);
Assert::notSame($node->items[0], $newNode->items[0]);

Assert::equal($node->items[0]->key, $newNode->items[0]->key);
Assert::notSame($node->items[0]->key, $newNode->items[0]->key);

Assert::equal($node->items[0]->value, $newNode->items[0]->value);
Assert::notSame($node->items[0]->value, $newNode->items[0]->value);

Assert::equal($node->items[0]->value->value, $newNode->items[0]->value->value);
Assert::notSame($node->items[0]->value->value, $newNode->items[0]->value->value);

Assert::equal($node->items[0]->value->attributes[0], $newNode->items[0]->value->attributes[0]);
Assert::notSame($node->items[0]->value->attributes[0], $newNode->items[0]->value->attributes[0]);
