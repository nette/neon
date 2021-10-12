<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Neon;


/**
 * Implements the Myers diff algorithm.
 *
 * Myers, Eugene W. "An O (ND) difference algorithm and its variations."
 * Algorithmica 1.1 (1986): 251-266.
 *
 * @internal
 */
final class Differ
{
	public const
		Keep = 0,
		Remove = 1,
		Add = 2;

	private $isEqual;


	public function __construct(callable $isEqual)
	{
		$this->isEqual = $isEqual;
	}


	/**
	 * Calculates diff from $old to $new.
	 * @template T
	 * @param  T[]  $old
	 * @param  T[]  $new
	 * @return array{self::Keep|self::Remove|self::Add, ?T, ?T}[]
	 */
	public function diff(array $old, array $new): array
	{
		[$trace, $x, $y] = $this->calculateTrace($old, $new);
		return $this->extractDiff($trace, $x, $y, $old, $new);
	}


	private function calculateTrace(array $a, array $b): array
	{
		$n = \count($a);
		$m = \count($b);
		$max = $n + $m;
		$v = [1 => 0];
		$trace = [];
		for ($d = 0; $d <= $max; $d++) {
			$trace[] = $v;
			for ($k = -$d; $k <= $d; $k += 2) {
				if ($k === -$d || ($k !== $d && $v[$k - 1] < $v[$k + 1])) {
					$x = $v[$k + 1];
				} else {
					$x = $v[$k - 1] + 1;
				}

				$y = $x - $k;
				while ($x < $n && $y < $m && ($this->isEqual)($a[$x], $b[$y])) {
					$x++;
					$y++;
				}

				$v[$k] = $x;
				if ($x >= $n && $y >= $m) {
					return [$trace, $x, $y];
				}
			}
		}
		throw new \Exception('Should not happen');
	}


	private function extractDiff(array $trace, int $x, int $y, array $a, array $b): array
	{
		$result = [];
		for ($d = \count($trace) - 1; $d >= 0; $d--) {
			$v = $trace[$d];
			$k = $x - $y;

			if ($k === -$d || ($k !== $d && $v[$k - 1] < $v[$k + 1])) {
				$prevK = $k + 1;
			} else {
				$prevK = $k - 1;
			}

			$prevX = $v[$prevK];
			$prevY = $prevX - $prevK;

			while ($x > $prevX && $y > $prevY) {
				$result[] = [self::Keep, $a[$x - 1], $b[$y - 1]];
				$x--;
				$y--;
			}

			if ($d === 0) {
				break;
			}

			while ($x > $prevX) {
				$result[] = [self::Remove, $a[$x - 1], null];
				$x--;
			}

			while ($y > $prevY) {
				$result[] = [self::Add, null, $b[$y - 1]];
				$y--;
			}
		}
		return array_reverse($result);
	}
}
