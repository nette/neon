<?php declare(strict_types=1);

/**
 * Checks out the specification with its corpus into tests/neon-spec, at the commit pinned in tests/neon-spec.lock.
 * The repository is the one of NEON_SPEC_REPOSITORY, or GitHub.
 */

$dir = __DIR__ . '/neon-spec';
$commit = trim((string) file_get_contents(__DIR__ . '/neon-spec.lock'));
$repository = getenv('NEON_SPEC_REPOSITORY') ?: 'https://github.com/nette/neon-specification.git';

run(file_exists("$dir/.git")
	? ['git', '-C', $dir, 'fetch', '--quiet', $repository]
	: ['git', 'clone', '--quiet', '--no-checkout', $repository, $dir]);
run(['git', '-C', $dir, 'checkout', '--quiet', '--detach', $commit]);


/** @param  list<string>  $command */
function run(array $command): void
{
	passthru(implode(' ', array_map(escapeshellarg(...), $command)), $code);
	if ($code) {
		exit($code);
	}
}
