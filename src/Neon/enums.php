<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Neon;


/**
 * Where the Evaluator meets a value: as a value, a key, the head of an entity, or an entity as a link of a chain.
 */
enum Place
{
	case Value;
	case Key;
	case EntityHead;
	case ChainLink;
}


/**
 * What a visitor of the Traverser returns to steer the walk.
 */
enum TraverseAction
{
	case SkipChildren;
	case Stop;
	case Remove;
}


/**
 * What happens to the comments a removed item owns: the one on its line and those directly above it; the comments
 * inside its value go with it.
 */
enum CommentPolicy
{
	case Drop;
	case MoveToNextToken;
}
