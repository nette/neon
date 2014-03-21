[NEON](http://ne-on.org): Nette Object Notation
===============================================

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

-----

[![Build Status](https://secure.travis-ci.org/nette/application.png?branch=master)](http://travis-ci.org/nette/application)
