[NEON](http://ne-on.org): Nette Object Notation
===============================================

[![Downloads this Month](https://img.shields.io/packagist/dm/nette/neon.svg)](https://packagist.org/packages/nette/neon)
[![Build Status](https://travis-ci.org/nette/neon.svg?branch=master)](https://travis-ci.org/nette/neon)
[![Coverage Status](https://coveralls.io/repos/github/nette/neon/badge.svg?branch=master)](https://coveralls.io/github/nette/neon?branch=master)
[![Latest Stable Version](https://poser.pugx.org/nette/neon/v/stable)](https://github.com/nette/neon/releases)
[![License](https://img.shields.io/badge/license-New%20BSD-blue.svg)](https://github.com/nette/neon/blob/master/license.md)


Introduction
------------

NEON is a human-readable data serialization language. It is commonly used for configuration files, but could be used in many applications where data is being stored

NEON is very similar to YAML.The main difference is that the NEON supports "entities" (so can be used e.g. to parse phpDoc annotations) and tab characters for indentation.
NEON syntax is a little simpler and the parsing is faster.

Documentation can be found on the [website](https://doc.nette.org/neon).

If you like Nette, **[please make a donation now](https://nette.org/donate)**. Thank you!


NEON language
-------------

Try NEON [in sandbox](https://ne-on.org)!

Example of NEON code:

```
# my web application config

php:
	date.timezone: Europe/Prague
	zlib.output_compression: yes  # use gzip

database:
	driver: mysql
	username: root
	password: beruska92

users:
	- Dave
	- Kryten
	- Rimmer
```

Installation
------------

The recommended way to install is via Composer:

```
composer require nette/neon
```

It requires PHP version 5.6 and supports PHP up to 7.3. The dev-master version requires PHP 7.0.


Usage
-----

`Nette\Neon\Neon` is a static class for encoding and decoding NEON files.

`Neon::encode()` returns $value encoded into NEON. Flags accepts Neon::BLOCK which formats NEON in multiline format.

```php
use Nette\Neon\Neon;
$neon = Neon::encode($value); // Returns $value encoded in NEON
$neon = Neon::encode($value, Neon::BLOCK); // Returns formatted $value encoded in NEON
```

`Neon::decode()` converts given NEON to PHP value:

```php
$value = Neon::decode('hello: world'); // Returns an array ['hello' => 'world']
```

Both methods throw `Nette\Neon\Exception` on error.


Editors plugins
---------------

- NetBeans IDE has built-in support
- [PhpStorm](https://plugins.jetbrains.com/plugin/7060?pr)
- [Visual Studio Code](https://marketplace.visualstudio.com/items?itemName=Kasik96.latte)
- [Emacs](https://github.com/Fuco1/neon-mode)


Other languages
---------------

- [Neon for Javascript](https://github.com/matej21/neon-js)
- [Neon for Python](https://github.com/paveldedik/neon-py)
