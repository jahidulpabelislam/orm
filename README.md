# ORM

[![CodeFactor](https://www.codefactor.io/repository/github/jahidulpabelislam/orm/badge)](https://www.codefactor.io/repository/github/jahidulpabelislam/orm)
[![Latest Stable Version](https://poser.pugx.org/jpi/orm/v/stable)](https://packagist.org/packages/jpi/orm)
[![Total Downloads](https://poser.pugx.org/jpi/orm/downloads)](https://packagist.org/packages/jpi/orm)
[![Latest Unstable Version](https://poser.pugx.org/jpi/orm/v/unstable)](https://packagist.org/packages/jpi/orm)
[![License](https://poser.pugx.org/jpi/orm/license)](https://packagist.org/packages/jpi/orm)
![GitHub last commit (branch)](https://img.shields.io/github/last-commit/jahidulpabelislam/orm/2.x.svg?label=last%20activity)

A super simple & lightweight ORM.

This has been kept very simple stupid (KISS), other than PHP type errors there is no validation (so use at your own risk), it will assume you are using it correctly. So please make sure to add your own validation if using user inputs in these queries.

## Dependencies

- PHP 8.0+
- Composer
- PHP PDO
- MySQL 5+
- [jpi/database](https://packagist.org/packages/jpi/database) v2
- [jpi/query](https://packagist.org/packages/jpi/query) v2

## Installation

Use [Composer](https://getcomposer.org/)

```bash
$ composer require jpi/orm 
```

## Usage

You will need to extend `\JPI\ORM\Entity` and then define the following

#### `getDatabase`

where you need to return an instance of `\JPI\Database` (which is just an extension of `PDO` - you can find out more [here](https://packagist.org/packages/jpi/database)).

#### `$table`

#### `$columnPrefix`

#### `$arrayColumnSeparator`

#### `$defaultOrderByColumn`

#### `$defaultOrderByASC`

#### `$dataMapping`



### Query builder

`newQuery` uses `\JPI\Database\Query\Builder` from [jpi/query](https://packagist.org/packages/jpi/query)).

### Query builder

- `save`

## Support

If you found this library interesting or useful please spread the word about this library: share on your socials, star on GitHub, etc.

If you find any issues or have any feature requests, you can open a [issue](https://github.com/jahidulpabelislam/orm/issues) or email [me @ jahidulpabelislam.com](mailto:me@jahidulpabelislam.com) :smirk:.

## Authors

- [Jahidul Pabel Islam](https://jahidulpabelislam.com/) [<me@jahidulpabelislam.com>](mailto:me@jahidulpabelislam.com)

## Licence

This module is licenced under the General Public Licence - see the [licence](LICENSE.md) file for details
