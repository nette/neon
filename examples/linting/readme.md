# Linting: a verdict for editors, CI and AI agents

`neon-lint` checks NEON files and says what is wrong and where, in a form both people and
machines read. It is the same command in every implementation of NEON, with the same output and
the same exit codes, so a hook written for one works with the others.

**You will learn:**

- the output, `<path>:<line>:<column>: <message>`, and the exit code that decides
- `--json` for tools, `--verbose` for people

```shell
php examples/linting/lint.php
```

## Running it (lint.php)

```
$ neon-lint config
config/local.neon:3:2: Duplicated key 'user'
config/services.neon:3:1: Unexpected end
exit code 1

$ neon-lint --json config/local.neon
[{"path":"config/local.neon","line":3,"column":2,"message":"Duplicated key 'user'"}]
exit code 1

$ neon-lint --verbose config/common.neon
OK 1 files
exit code 0

```

Exit code 0 means every file is valid, 1 at least one finding (or no file found), 2 a wrong
invocation. A directory is searched for `*.neon`, skipping `vendor`, `node_modules` and hidden
directories. `--check` also verifies that printing the parsed tree gives back the file, which is
the promise of this library; on the command line the same runs as `vendor/bin/neon-lint config/`.
