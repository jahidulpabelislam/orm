# ORM

[![CodeFactor](https://www.codefactor.io/repository/github/jahidulpabelislam/orm/badge)](https://www.codefactor.io/repository/github/jahidulpabelislam/orm)
[![Latest Stable Version](https://poser.pugx.org/jpi/orm/v/stable)](https://packagist.org/packages/jpi/orm)
[![Total Downloads](https://poser.pugx.org/jpi/orm/downloads)](https://packagist.org/packages/jpi/orm)
[![Latest Unstable Version](https://poser.pugx.org/jpi/orm/v/unstable)](https://packagist.org/packages/jpi/orm)
[![License](https://poser.pugx.org/jpi/orm/license)](https://packagist.org/packages/jpi/orm)
![GitHub last commit (branch)](https://img.shields.io/github/last-commit/jahidulpabelislam/orm/2.x.svg?label=last%20activity)

A super simple & lightweight ORM.

This has been kept very simple stupid (KISS), there is little to no validation, and it will assume you are using it correctly. So please make sure to add your own validation if using user inputs in the queries.

I would advise against using this on real-world/live applications...but feel free to use in your own demo/experimental projects.

SO TO BE VERY CLEAR USE AT YOUR OWN RISK!

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

- Extend `\JPI\ORM\Entity`
- `$dataMapping`
- implement `getDatabase`

To create an instance, you will need an instance of `\JPI\Database` (if unfamiliar you can read about that [here](https://packagist.org/packages/jpi/database)) which is the first parameter, and the database table name as the second parameter. The same instance can be used multiple times as long as it's for the same database.

- `$table`
- `$columnPrefix`
- `$arrayColumnSeparator`
- `$defaultOrderByColumn`
- `$defaultOrderByASC`

### Query builder
`newQuery` uses `\JPI\Database\Query\Builder` from [jpi/query](https://packagist.org/packages/jpi/query)).

## Support

If you found this library interesting or useful please spread the word about this library: share on your socials, star on GitHub, etc.

If you find any issues or have any feature requests, you can open a [issue](https://github.com/jahidulpabelislam/orm/issues) or email [me @ jahidulpabelislam.com](mailto:me@jahidulpabelislam.com) :smirk:.

## Authors

-   [Jahidul Pabel Islam](https://jahidulpabelislam.com/) [<me@jahidulpabelislam.com>](mailto:me@jahidulpabelislam.com)

## Licence

This module is licenced under the General Public Licence - see the [licence](LICENSE.md) file for details
