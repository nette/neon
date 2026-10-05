<?php declare(strict_types=1);

/**
 * Finding your way in the tree: items by path, nodes by class, the slots of an item, and positions.
 *
 * Usage: php examples/tree/navigate.php
 */

use Nette\Neon\Neon;
use Nette\Neon\Nodes\ArrayItemNode;
use Nette\Neon\Nodes\EntityNode;
use Nette\Neon\Nodes\StringNode;

require __DIR__ . '/../bootstrap.php';

$doc = Neon::parse(sample('
	database:
		dsn: \'mysql:host=localhost\'
		user: root   # change me
	services:
		- App\Mailer(@smtp)
		cache: App\Cache(%tempDir%)
'));

// an item by the path of keys, the same keys decode() gives
$user = $doc->findItem(['database', 'user']);
echo 'key:      ', $user->getKeyValue(), "\n";
echo 'value:    ', $user->getValue(), "\n";
echo 'position: ', $user->getPosition(), "\n";
echo 'comment:  ', implode(', ', array_map(fn($c) => $c->getCommentText(), $user->getTrailingComments())), "\n";

// the slots of an item, each a node or a token
echo 'slots:    ', $user->key::class, ', ', json_encode($user->colon->text), ', ', $user->value::class, "\n";

// nodes by class, anywhere below
foreach ($doc->find(EntityNode::class) as $entity) {
	$item = $entity->findAncestor(ArrayItemNode::class);
	echo 'entity:   ', $item->getKeyValue() ?? '(no key)', ' -> ', $entity->value->toValue(), "\n";
}
echo 'strings:  ', json_encode(array_map(fn(StringNode $node) => $node->toValue(), $doc->find(StringNode::class))), "\n";
