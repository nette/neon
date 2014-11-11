[NEON](http://ne-on.org): Nette Object Notation
===============================================

[![Downloads this Month](https://img.shields.io/packagist/dm/nette/neon.svg)](https://packagist.org/packages/nette/neon)
[![Build Status](https://travis-ci.org/nette/neon.svg?branch=master)](https://travis-ci.org/nette/neon)

NEON is very similar to YAML.The main difference is that the NEON supports "entities"
(so can be used e.g. to parse phpDoc annotations) and tab characters for indentation.
NEON syntax is a little simpler and the parsing is faster.

Example of Neon code:

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

Links:
- [Neon sandbox](http://ne-on.org)
- [Neon for Javascript](https://github.com/matej21/neon-js)
