<?php declare(strict_types=1);


/**
 * Test: nodes of the tree, built by hand: ownership and clone.
 */
use Nette\Neon\Neon;
use Nette\Neon\Nodes\ArrayItemNode;
use Nette\Neon\Nodes\DocumentNode;
use Nette\Neon\Nodes\InlineArrayNode;
use Nette\Neon\Nodes\LiteralNode;
use Nette\Neon\Token;
use Nette\Neon\Trivia;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


function literal(string $text): LiteralNode
{
	return new LiteralNode(Token::fromText($text));
}


/** `[a,b:c]` */
function inline(): InlineArrayNode
{
	$comma = Token::fromText(',');
	$colon = Token::fromText(':');
	return new InlineArrayNode(Token::fromText('['), [
		new ArrayItemNode(null, null, null, literal('a'), $comma),
		new ArrayItemNode(null, literal('b'), $colon, literal('c')),
	], Token::fromText(']'));
}


test('a tree built by hand prints and knows its parents', function () {
	$array = inline();
	$document = new DocumentNode($array, Token::fromText(''));
	Assert::same('[a,b:c]', $document->print());
	Assert::same('[a,b:c]', (string) $array);
	Assert::same($document, $array->parent);
	Assert::same($array, $array->items[0]->parent);
	Assert::same($array->items[0], $array->items[0]->value?->parent);
	Assert::same(['0', 'b'], $array->getItemKeys());
});


test('the guarded properties cannot be written from outside', function () {
	$array = inline();
	Assert::exception(fn() => $array->items[] = $array->items[0], Error::class, '%a%protected(set)%a%');
	Assert::exception(fn() => $array->items[0]->value = null, Error::class, '%a%protected(set)%a%');
	Assert::exception(fn() => serialize($array), LogicException::class);
	Assert::exception(fn() => serialize(Token::fromText('a')), LogicException::class);
});


test('clone is deep and detached', function () {
	$document = new DocumentNode(inline(), Token::fromText(''));
	$copy = clone $document;
	Assert::same($document->print(), $copy->print());
	Assert::null($copy->parent);
	$original = array_map(spl_object_id(...), $document->getTokens());
	Assert::same([], array_intersect($original, array_map(spl_object_id(...), $copy->getTokens())));
	Assert::same($copy, $copy->value?->parent);
	Assert::same($document, $document->value?->parent);

	$item = clone $document->value?->items[1];
	Assert::null($item?->parent);
	Assert::same('b:c', $item?->print());
});
