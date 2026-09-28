<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Neon;

use function count;


/**
 * Makes an edit atomic: while it runs, every raw write of the tree records how to undo itself, and a failure
 * undoes them all, so that a refused edit leaves the target and a donor as they were. A nested call is a
 * savepoint: it undoes only its own writes and lets the failure go on.
 * @internal
 */
final class Journal
{
	/** @var list<\Closure(): void>|null */
	private static ?array $undo = null;


	/**
	 * @template T
	 * @param  callable(): T  $operation
	 * @return T
	 */
	public static function atomic(callable $operation): mixed
	{
		$outer = self::$undo === null;
		self::$undo ??= [];
		$savepoint = count(self::$undo);
		try {
			return $operation();

		} catch (\Throwable $e) {
			$undo = self::$undo ?? [];
			for ($i = count($undo) - 1; $i >= $savepoint; $i--) {
				$undo[$i]();
			}
			self::$undo = array_slice($undo, 0, $savepoint);
			throw $e;

		} finally {
			if ($outer) {
				self::$undo = null;
			}
		}
	}


	/** Whether an atomic() operation runs, so that a raw write has to note how to undo itself. */
	public static function isRecording(): bool
	{
		return self::$undo !== null;
	}


	/**
	 * Notes how to undo a raw write; outside atomic() it is not needed.
	 * @param  \Closure(): void  $undo
	 */
	public static function record(\Closure $undo): void
	{
		if (self::$undo !== null) {
			self::$undo[] = $undo;
		}
	}
}
