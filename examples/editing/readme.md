# Editing a configuration file: the diff is only what you changed

This is what the library is for. A configuration file is written by people: comments, blank lines,
tabs or spaces, a hexadecimal number, `yes` instead of `true`. A tool that loads it, changes one
option and writes it back has to keep all of that, or nobody will let it touch their files. Each
program below edits a file and prints the diff, and the diff is the change and nothing else.

**You will learn:**

- how to change a value by its path, and why writing the same value changes nothing
- how a new key gets the indentation and the line endings of its neighbours
- what an item takes with it when it is removed, and what stays
- how inline arrays keep their separators and their trailing comma
- how a scalar becomes a block and back, and how to edit an entity of the DI container

```shell
php examples/editing/values.php
php examples/editing/adding.php
php examples/editing/removing.php
php examples/editing/inline.php
php examples/editing/structures.php
```

The editing methods of `DocumentNode` take a **path**, a list of keys from the root, the same keys
`Neon::decode()` gives (`['database', 'port']`, `['services', 0]`). They are shorthands for the
methods of the item the path finds: `$doc->setValue(['database', 'port'], 3307)` is
`$doc->findItem(['database', 'port'])->setValue(3307)`, when the item exists.

## Changing values (values.php)

```
- 	host: localhost   # the server
- 	port: 0x0CEA
- 	debug: yes
- 	user: 'root'
- 	password: "s3cret"
+ 	host: db.example.com   # the server
+ 	port: 0x0CEB
+ 	debug: no
+ 	user: 'admin'
+ 	password: "it's \"new\""
```

Only the token of the value changes; the spaces and the comment after it are the trivia of the
token and stay. A new value is written **in the style of the old one** where the style can be
derived: the port stays hexadecimal with its digits in upper case, `yes` becomes `no` rather than
`false`, a string keeps its quotes and is escaped for them. Where it cannot, the value is written as
`Neon::encode()` writes it.

The last call writes `30` to a timeout that is `30` already, and the diff does not mention it:
**writing an equal value is a no-op**. A form that saves all its fields therefore does not rewrite
`0x10` as `16` or `YES` as `true` in the fields nobody touched. Equal means equal as PHP reads the
file: an empty array written over `{}` keeps it, and so does a date over its text.

## Adding items (adding.php)

```
+     admin: admin@example.com
+ 	- App\Model\OrderFacade
+ 	console: Contributte\Console\DI\ConsoleExtension

No item 'parametres' in the path parametres.admin
No item 'amdin' in the path parameters.amdin, and create is false
```

`setValue()` on a path whose last key does not exist adds the item, at the end of its parent, with
the indentation of its siblings: four spaces under `parameters`, a tab under `services`. `addItem()`
adds an item without a key, a dash in a block. The empty `extensions:` becomes a block, indented by
the unit the file uses around it.

A key in the middle of the path that does not exist is always an error, so that a typo in a
migration does not grow a new section; `create: false` makes a missing last key an error too.

## Removing items (removing.php)

```
- 	# legacy, will be removed
- 	driver: mysql   # see #123

- # --- Mail ---
- mail:
- 	smtp: true

- mail:
- 	smtp: true

- 	smtp: true
- queue:
- 	- send
+ queue: []
```

An item takes with it the comment on its line and the comments **directly above** it: they describe
the key, and above the next one they would lie. A comment separated from the item by a blank line,
like `# --- Database ---`, is a heading of what follows and stays.

`# --- Mail ---` stands right above `mail:`, so removing `mail` removes it as well. When you move or
replace sections and want to keep such a heading, say so: `CommentPolicy::MoveToNextToken` leaves
the comments above whatever follows.

Removing the last item **keeps the collection**: `queue` becomes `[]`, because in a configuration an
empty list and no list at all often mean different things. An option for which they mean the same is
marked `nullable`, and then removing its last item leaves `mail:` with no value.

## Inline arrays (inline.php)

```
- roles: [guest, member]
+ roles: [root, guest, member, admin]
+ 	gamma,
- limits: {cpu: 2, memory: 512}
- empty: []
+ limits: {memory: 512, disk: 100}
+ empty: [first]
```

In an inline array the comma belongs to the item before it, so an item added at the end gives a
comma to its predecessor and an item added at the start takes one. An array spread over several
lines gets the new item on a line of its own, indented like its neighbours, with the trailing comma
they have.

## Structures (structures.php)

```
-     mapping: App\*Module\Presenters\*Presenter
+     mapping:
+         *: App\*Module\**Presenter
-     mailer: App\Mailer(@smtp, timeout: 30)
+     mailer: App\Mailer(@smtp, timeout: 60)
-     expiration: 14 days
-     autoStart: true
+     - services.neon
+     - local.neon
```

A value written into a block item is written as a block, indented by one more unit than the item;
the unit is found near the place of the edit, here four spaces. `null` removes the value and leaves
the key, as in `session:`. A path continues into the attributes of an entity, so the timeout of the
mailer is changed like any other key.
