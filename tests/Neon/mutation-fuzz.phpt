<?php declare(strict_types=1);

/**
 * Test: random sequences of edits over the documents of the corpus. After each edit the tree must stay valid and
 * its value must be what the same edit does to an independent model; an edit the library refuses must change
 * nothing, neither in the document nor in a donor of a node.
 */

use Nette\Neon\{CommentPolicy, Entity, Neon, Node, Token};
use Nette\Neon\Nodes\{ArrayNode, BlockArrayNode, DocumentNode, EntityChainNode, EntityNode, InlineArrayNode, ValueNode};
use Tester\Assert;
use Tests\{Corpus, Guarantees, Typed};

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../helpers/guarantees.php';


// an ordered model: an array in the order of the document, an empty map is an empty stdClass


function normalize(array $entries): array|stdClass
{
	return $entries ?: new stdClass;
}


function fromNode(?ValueNode $node): mixed
{
	if ($node instanceof ArrayNode) {
		$keys = $node->getItemKeys();
		$entries = [];
		foreach ($node->items as $i => $item) {
			$entries[$keys[$i]] = fromNode($item->value); // an overwritten key keeps its first position
		}
		return $entries || ($node instanceof InlineArrayNode && $node->openBracket->kind === Token::OpenBrace) ? normalize($entries) : [];
	} elseif ($node instanceof EntityChainNode) {
		return new Entity(Neon::Chain, array_map(fromNode(...), $node->entities));
	} elseif ($node instanceof EntityNode) {
		return new Entity(fromNode($node->value), $node->attributes ? (array) fromNode($node->attributes) : []);
	}
	return $node?->toValue();
}


function fromHost(mixed $value): mixed
{
	return match (true) {
		is_array($value) => array_map(fromHost(...), $value),
		$value instanceof Entity => new Entity(fromHost($value->value), (array) fromHost($value->attributes)),
		$value instanceof stdClass => normalize(array_map(fromHost(...), get_object_vars($value))),
		default => $value,
	};
}


/** The model after writing the value of PHP over the old one: equal as PHP reads it is no change, an empty stdClass is a map. */
function project(mixed $value, mixed $old): mixed
{
	$new = fromHost($value);
	return match (true) {
		isEmptyMap($new) => isEmptyMap($old) ? $old : $new,
		same(toPlain($new), toPlain($old)) => $old,
		default => $new,
	};
}


/** The model as Neon::decode() gives it. */
function toPlain(mixed $model): mixed
{
	return match (true) {
		isEmptyMap($model) => [],
		is_array($model) => array_map(toPlain(...), $model),
		$model instanceof Entity => new Entity(toPlain($model->value), toPlain($model->attributes)),
		default => $model,
	};
}


/** The empty map of the model; an Entity is a stdClass too. */
function isEmptyMap(mixed $model): bool
{
	return $model instanceof stdClass && !$model instanceof Entity;
}


function same(mixed $a, mixed $b): bool
{
	return match (true) {
		isEmptyMap($a) || isEmptyMap($b) => isEmptyMap($a) && isEmptyMap($b),
		$a instanceof Entity && $b instanceof Entity => same($a->value, $b->value) && same($a->attributes, $b->attributes),
		is_array($a) && is_array($b) => array_keys($a) === array_keys($b) && array_all($a, fn($v, $k) => same($v, $b[$k])),
		default => Typed::same($a, $b),
	};
}


/** The array of the model at the path: a list, a map or the attributes of an entity. */
function containerAt(mixed $model, array $path): mixed
{
	foreach ($path as $segment) {
		$model = arrayOfModel($model)[$segment] ?? null;
	}
	return arrayOfModel($model);
}


function arrayOfModel(mixed $node): mixed
{
	return match (true) {
		$node instanceof Entity => $node->attributes,
		$node instanceof stdClass => [],
		default => $node,
	};
}


/** Replaces the array at the path by a new one, with the edit applied. */
function update(mixed $model, array $path, Closure $edit): mixed
{
	$entries = (array) arrayOfModel($model);
	if ($path) {
		$key = array_shift($path);
		$entries[$key] = update($entries[$key], $path, $edit);
		$res = normalize($entries);
	} else {
		$res = $edit($entries);
	}
	return $model instanceof Entity ? new Entity($model->value, (array) arrayOfModel($res)) : $res;
}


// the run


function random(int $seed): Closure
{
	return function () use (&$seed): float {
		$seed = ($seed * 1_103_515_245 + 12345) & 0x7FFFFFFF;
		return $seed / 0x80000000;
	};
}


/** Paths of all items the decoded value can reach, with the items. */
function itemPaths(DocumentNode $document): array
{
	$res = [];
	$walk = function (?Node $node, array $path) use (&$walk, &$res): void {
		$array = $node instanceof EntityNode ? $node->attributes : $node;
		if (!$array instanceof ArrayNode) {
			return;
		}
		$keys = $array->getItemKeys();
		foreach ($array->items as $i => $item) {
			if ($array->findItem($keys[$i]) === $item) {
				$itemPath = [...$path, $keys[$i]];
				$res[] = [$itemPath, $item];
				$walk($item->value, $itemPath);
			}
		}
	};
	$walk($document->value, []);
	return $res;
}


function isInside(Node $node, Node $ancestor): bool
{
	for ($n = $node; $n; $n = $n->parent) {
		if ($n === $ancestor) {
			return true;
		}
	}
	return false;
}


$values = [null, true, 0, 42, -1.5, 'x', 'two words', 'a: b', '-.5', "line\nbreak", [], new stdClass, [1, 2], ['k' => 'v'],
	['a' => 1, 'b' => [true]], new Entity('E', [1]), new DateTimeImmutable('2024-02-29')];
$keyNames = ['new', 'k', 'two words', '5', 'x:y'];
$donorTexts = ['scalar', '[1, 2]', '{a: 1}', "x: 1\ny: [2]\n", "- p\n- q\n", 'E(a: 1)', 'a()b()', "'quoted'"];

$inputs = [];
foreach (['roundtrip', 'decode', 'mutations', 'cst'] as $category) {
	foreach (Corpus::load($category) as $case) {
		try {
			Neon::decode($input = (string) $case->read('.neon'));
			$inputs[] = $input;
		} catch (Nette\Neon\Exception) {
		}
	}
}
array_push($inputs, '', "# only a comment\n", "\u{FEFF}", "42\n");

$count = ['attempts' => 0, 'applied' => 0, 'noops' => 0, 'refused' => 0, 'skipped' => 0];
$first = (int) (getenv('FUZZ_SEED') ?: 1);
$last = getenv('FUZZ_SEED') ? $first : (int) (getenv('FUZZ_SEEDS') ?: 1500);
for ($seed = $first; $seed <= $last; $seed++) {
	$next = random($seed);
	$pick = fn(array $list) => $list[(int) floor($next() * count($list))];
	$document = Neon::parse($pick($inputs));
	$model = fromNode($document->value);

	for ($step = 0; $step < 4; $step++) {
		$count['attempts']++;
		$paths = itemPaths($document);
		$kind = $paths ? (int) floor($next() * 9) : 5;
		[$path, $item] = $paths ? $pick($paths) : [[], null];
		$parentPath = array_slice($path, 0, -1);
		$lastKey = $path ? $path[count($path) - 1] : null;
		$parent = $paths ? containerAt($model, $parentPath) : null;
		$before = $document->print();
		$description = "seed $seed, step $step: "
			. ['set', 'add', 'remove', 'setKey', 'move', 'root', 'node', 'key node', 'refused'][$kind]
			. ' '
			. json_encode($path)
			. " in\n$before";
		$donor = $donorNode = $expectRefusal = null;

		if ($kind === 0) { // a value of PHP
			$value = $pick($values);
			$operation = fn() => $document->setValue($path, $value);
			$after = fn() => update($model, $parentPath, function ($entries) use ($lastKey, $value) {
				$entries[$lastKey] = project($value, $entries[$lastKey] ?? null);
				return normalize($entries);
			});

		} elseif ($kind === 1) { // a new item
			$value = $pick($values);
			$array = $item->parent;
			assert($array instanceof ArrayNode);
			if (is_array($parent) && array_is_list($parent) && $next() < 0.5) {
				$operation = fn() => $array->addItem($value);
				$after = fn() => update($model, $parentPath, fn($entries) => normalize([...$entries, fromHost($value)]));
			} else {
				$key = $pick($keyNames);
				if ((is_array($parent) && array_is_list($parent)) || array_key_exists($key, (array) $parent)) {
					$expectRefusal = '~Duplicated key~';
					$operation = fn() => $array->addItem($value, is_array($parent) && array_is_list($parent) ? '0' : $key);
					$after = fn() => $model;
				} else {
					$operation = fn() => $document->setValue([...$parentPath, $key], $value);
					$after = fn() => update($model, $parentPath, function ($entries) use ($key, $value) {
						$entries[$key] = fromHost($value);
						return normalize($entries);
					});
				}
			}

		} elseif ($kind === 2) { // removing
			$array = $item->parent;
			assert($array instanceof ArrayNode);
			$isList = is_array($parent) && array_is_list($parent);
			if ($isList && array_any($array->items, fn($sibling) => $sibling->key)) {
				$count['skipped']++; // keys written in a list stay, so the model cannot just renumber
				continue;
			}
			$comments = $next() < 0.5 ? CommentPolicy::Drop : CommentPolicy::MoveToNextToken;
			$keys = $array->getItemKeys();
			$emptied = array_all($array->items, fn($other, $i) => $other === $item || $keys[$i] === (string) $lastKey);
			$empty = $array instanceof InlineArrayNode
				? ($array->openBracket->kind === Token::OpenBrace ? new stdClass : [])
				: ($item->key ? new stdClass : []);
			$operation = fn() => $document->removeItem($path, $comments);
			$after = fn() => update($model, $parentPath, function ($entries) use ($emptied, $empty, $lastKey, $isList) {
				if ($emptied) {
					return $empty;
				}
				unset($entries[$lastKey]);
				return $isList ? array_values($entries) : normalize($entries);
			});

		} elseif ($kind === 3) { // renaming
			$key = $pick($keyNames);
			if (!$item->key || (is_array($parent) && array_is_list($parent))) {
				$count['skipped']++;
				continue;
			} elseif (array_key_exists($key, (array) $parent) && $key !== (string) $lastKey) {
				$expectRefusal = '~Duplicated key~';
				$after = fn() => $model;
			} else {
				$after = fn() => update($model, $parentPath, fn($entries) => normalize(renameKey($entries, $lastKey, $key)));
			}
			$operation = fn() => $document->setKey($path, $key);

		} elseif ($kind === 4) { // a keyed item moves into another map
			[$targetPath, $targetItem] = $pick($paths);
			$targetArray = $targetItem->value instanceof EntityNode ? $targetItem->value->attributes : $targetItem->value;
			$key = $item->getKeyValue();
			$targetModel = containerAt($model, $targetPath);
			if ($key === null || (is_array($parent) && array_is_list($parent)) || !$targetArray instanceof ArrayNode || isInside(
				$targetItem,
				$item,
			)
				|| !((is_array($targetModel) && $targetModel && !array_is_list($targetModel)) || isEmptyMap($targetModel))
				|| array_key_exists($key, (array) $targetModel) || count($item->parent->items) === 1
			) {
				$count['skipped']++;
				continue;
			}
			$position = (int) floor($next() * (count($targetArray->items) + 1));
			if ($targetArray instanceof InlineArrayNode && ($item->bullet || $item->value instanceof BlockArrayNode)) {
				$expectRefusal = '~cannot stand~';
				$copy = clone $item;
				$operation = fn() => $targetArray->insert($position, $copy);
				$after = fn() => $model;
			} else {
				$value = ((array) $parent)[$key];
				$operation = function () use ($item, $targetArray, $position) {
					$item->remove();
					$targetArray->insert($position, $item);
				};
				$after = function () use ($model, $parentPath, $targetPath, $key, $value, $position) {
					$removed = update($model, $parentPath, function ($entries) use ($key) {
						unset($entries[$key]);
						return normalize($entries);
					});
					return update(
						$removed,
						$targetPath,
						fn($entries) => normalize(
							array_slice($entries, 0, $position, true) + [$key => $value] + array_slice($entries, $position, null, true),
						),
					);
				};
			}

		} elseif ($kind === 5) { // the root
			$value = $pick($values);
			$operation = fn() => $document->setValue([], $value);
			$after = fn() => project($value, $model);

		} elseif ($kind === 6) { // a node from another document into the value slot
			$donor = Neon::parse($pick($donorTexts));
			$inline = $item->parent instanceof InlineArrayNode || (!$item->key && !$item->bullet);
			if ($next() < 0.3) {
				$node = $donor->value;
				$expectRefusal = '~parent|block~'; // not detached first
				$operation = fn() => $item->setValue($node);
				$after = fn() => $model;
			} else {
				$value = fromNode($donor->value);
				$node = $donorNode = $donor->value->detach();
				if ($node instanceof BlockArrayNode && $inline) {
					$expectRefusal = '~block~i';
					$after = fn() => $model;
				} else {
					$after = fn() => update($model, $parentPath, function ($entries) use ($lastKey, $value) {
						$entries[$lastKey] = $value;
						return normalize($entries);
					});
				}
				$operation = fn() => $item->setValue($node);
			}

		} elseif ($kind === 7) { // a key node
			$key = $pick(['new', 'k', 'zz']);
			if (!$item->key || (is_array($parent) && array_is_list($parent))) {
				$count['skipped']++;
				continue;
			} elseif ($next() < 0.3) {
				$expectRefusal = '~cannot be empty~';
				$operation = fn() => $item->key->detach();
				$after = fn() => $model;
			} elseif (array_key_exists($key, (array) $parent) && $key !== (string) $lastKey) {
				$expectRefusal = '~Duplicated key~';
				$operation = fn() => $item->key->replaceWith(Neon::parse($key)->value->detach());
				$after = fn() => $model;
			} else {
				$operation = fn() => $item->key->replaceWith(Neon::parse($key)->value->detach());
				$after = fn() => update($model, $parentPath, fn($entries) => normalize(renameKey($entries, $lastKey, $key)));
			}

		} else { // operations refused by their shape
			$array = $item->parent;
			assert($array instanceof ArrayNode);
			$node = Neon::parse("x: 1\n")->value->detach();
			if ($array instanceof InlineArrayNode) {
				$expectRefusal = '~block~i';
				$operation = fn() => $array->addItem($node);
			} else {
				$expectRefusal = '~out of the array~';
				$operation = fn() => $array->addItem(1, position: count($array->items) + 1);
			}
			$after = fn() => $model;
		}

		$unchanged = Guarantees::snapshot($document, ...array_filter([$donor, $donorNode]));
		try {
			$operation();
			if ($expectRefusal) {
				Assert::fail("$description\nexpected a refusal $expectRefusal");
			}
		} catch (Throwable $e) {
			if (!$expectRefusal || !preg_match($expectRefusal, $e::class . ': ' . $e->getMessage())) {
				throw new LogicException($description . "\n" . $e::class . ': ' . $e->getMessage(), 0, $e);
			}
			$unchanged();
			$count['refused']++;
		}
		$model = $after();
		if (!$expectRefusal) {
			$count[$document->print() === $before ? 'noops' : 'applied']++;
		}
		Guarantees::checkTree($document);
		if ($donor) {
			Guarantees::checkTree($donor);
		}
		Assert::true(
			same(fromNode(Neon::parse($document->print())->value), $model),
			"$description\nafter:\n" . $document->print() . "\nmodel: " . var_export($model, true),
		);
	}
}


function renameKey(array $entries, int|string $old, string $new): array
{
	$res = [];
	foreach ($entries as $k => $v) {
		$res[(string) $k === (string) $old ? $new : $k] = $v;
	}
	return $res;
}


if (getenv('FUZZ_SEEDS')) {
	echo json_encode($count), "\n";
}
Assert::true($count['applied'] > $count['attempts'] / 2);
Assert::true($count['refused'] > $count['attempts'] / 20);
