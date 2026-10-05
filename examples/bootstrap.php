<?php declare(strict_types=1);

// Helpers shared by the examples.

require __DIR__ . '/../vendor/autoload.php';

date_default_timezone_set('Europe/Prague');


/** Removes the common indentation and the first and last line break of a heredoc-like text. */
function sample(string $text): string
{
	$lines = explode("\n", preg_replace(['~^\n~', '~\n[\t ]*$~D'], '', $text));
	$indent = min(array_map(fn($line) => strspn($line, "\t"), array_filter($lines, fn($line) => trim($line) !== '')));
	return implode("\n", array_map(fn($line) => substr($line, $indent), $lines)) . "\n";
}


/** Prints the lines that differ, as - and +, so you see that nothing else changed. */
function printDiff(string $before, string $after): void
{
	$a = explode("\n", $before);
	$b = explode("\n", $after);
	$lcs = array_fill(0, count($a) + 1, array_fill(0, count($b) + 1, 0)); // longest common subsequence of lines
	for ($i = count($a) - 1; $i >= 0; $i--) {
		for ($j = count($b) - 1; $j >= 0; $j--) {
			$lcs[$i][$j] = $a[$i] === $b[$j] ? $lcs[$i + 1][$j + 1] + 1 : max($lcs[$i + 1][$j], $lcs[$i][$j + 1]);
		}
	}

	$show = fn(string $line) => str_replace("\r", '\r', $line);
	for ($i = $j = 0; $i < count($a) || $j < count($b);) {
		if ($i < count($a) && $j < count($b) && $a[$i] === $b[$j]) {
			$i++;
			$j++;
		} elseif ($i < count($a) && ($j === count($b) || $lcs[$i + 1][$j] >= $lcs[$i][$j + 1])) {
			echo '- ', $show($a[$i++]), "\n";
		} else {
			echo '+ ', $show($b[$j++]), "\n";
		}
	}
}
