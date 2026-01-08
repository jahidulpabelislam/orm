# ORM

[![CodeFactor](https://www.codefactor.io/repository/github/jahidulpabelislam/orm/badge)](https://www.codefactor.io/repository/github/jahidulpabelislam/orm)
[![Latest Stable Version](https://poser.pugx.org/jpi/orm/v/stable)](https://packagist.org/packages/jpi/orm)
[![Total Downloads](https://poser.pugx.org/jpi/orm/downloads)](https://packagist.org/packages/jpi/orm)
[![Latest Unstable Version](https://poser.pugx.org/jpi/orm/v/unstable)](https://packagist.org/packages/jpi/orm)
[![License](https://poser.pugx.org/jpi/orm/license)](https://packagist.org/packages/jpi/orm)
![GitHub last commit (branch)](https://img.shields.io/github/last-commit/jahidulpabelislam/orm/2.x.svg?label=last%20activity)

A super simple & lightweight ORM following the active record pattern.

This has been kept very simple stupid (KISS), with minimal validation (PHP type errors only) to reduce complexity in the library and maximize performance for consumer developers. Therefore, please make sure to add your own validation if using user inputs in any database queries.

## Features

- Easy to configure for your project's entities
- Supporting scalar types, array, and date/time and relationships `Many-to-One`, `One-to-Many` and `One-to-One`
- Fluent, chainable API for CRUD operations, using integrated query builder from [jpi/query](https://packagist.org/packages/jpi/query)

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

### Properties and Methods for Setup

You will need to extend `\JPI\ORM\Entity` and then define the following:

#### `getDatabase(): \JPI\Database`

This method must be implemented to provide the database connection for this entity. `\JPI\Database` is just an extension of `PDO` - you can find out more [here](https://packagist.org/packages/jpi/database).

#### `$table: string`

The database table name for this entity.

#### `$dataMapping: array`

This array defines the structure of your entity and maps to your database columns. Each key is a column name and the value is an array with:

- `type` (required): One of: `string`, `int`, `float`, `array`, `date`, `date_time`, `belongs_to`, `has_one`, `has_many`
- `default_value`
- `separator`: The separator for `array` type columns when stored as delimited strings (defaults to `","`)
- `entity`: The related entity class name (required for relationship types)
- `column`
    - The database column behind this for `belongs_to` type (defaults to `{key}_id`)
    - The key in related entity for `has_many` & `has_one` types that links back to this
- `cascade_delete`: Whether to delete related entities when this entity is deleted (for `has_one` and `has_many` types)
- `cascade_clone`: Whether to clone related entities when this entity is clone via `clone`, else it sets to null/empty - essentially creates new records with same data (for `has_one` and `has_many` types)

```php
protected static array $dataMapping = [
    "name" => [
        "type" => "string",
    ],
    "sku" => [
        "type" => "string",
    ],
    "price" => [
        "type" => "float",
        "default_value" => 0.00,
    ],
    "categories" => [
        "type" => "array",
        "separator" => ",", // Optional, defaults to ","
    ],
    "created_at" => [
        "type" => "date_time",
    ],
];
```

#### `$columnPrefix: string` - optional

Some database designers like to prefix their table columns. For example, the `products` table might have columns like `product_id` & `product_name` instead of `id` & `name`. Set this property to add that prefix automatically.

Note: the first underscore is required.

#### `$defaultOrderByColumn: string` - optional

The default column to order results by when selecting records and haven't specified an order. Default is `id`.

#### `$defaultOrderByASC: bool` - optional

Whether the default ordering should be ascending. Default is `true`.

### Complete Example

```php
...
class Product extends \JPI\ORM\Entity {

    protected static string $table = "products";

    protected static array $dataMapping = [
        "name" => [
            "type" => "string",
        ],
        "sku" => [
            "type" => "string",
        ],
        "price" => [
            "type" => "float",
        ],
        "stock" => [
            "type" => "int",
        ],
        "created_at" => [
            "type" => "date_time",
        ],
    ];

    public static function getDatabase(): \JPI\Database {
        return new \JPI\Database("mysql:host=localhost;dbname=shop", "username", "password");
    }

    ...
}
```

### Usage

#### Retrieving Entities

**`getById(int $id): ?static`** - Get an entity by its ID.

**`newQuery(): QueryBuilder`** - Get a query builder instance for advanced queries. See [Query Builder](#query-builder) section for examples.

#### Accessing Entity Data

You can get and set entity values using simple property access, these are the keys from `$dataMapping`. When setting the value must be value for the type defined or null.

#### Creating and Saving Entities

**`factory(?array $data = null): static`** - Create a new entity instance, and optionally set initial data in one call.

**`insert(array $data): static`** - Create and save an entity in one call.

**`save(): bool`** - Save (insert or update) the entity to the database.

#### Deleting Entities

**`delete(): bool`** - Delete the entity from the database.

#### Utility Methods

**`isLoaded(): bool`** - Check if the entity has been loaded from or saved to the database.

**`isDeleted(): bool`** - Check if the entity has been deleted.

**`toArray(): array`** - Convert the entity to an array.

**`reload(): void`** - Reload the entity from the database.

## Query Builder

The query builder (accessed via `newQuery()`) provides a fluent interface for building database queries. It uses `\JPI\Database\Query\Builder` from [jpi/query](https://packagist.org/packages/jpi/query), see documentation there for full details on available query methods.

## Relationships

The ORM supports three types of relationships:

#### `belongs_to` - Many-to-One

```php
class Order extends Entity {
    ...
    protected static array $dataMapping = [
        ...
        "customer" => [
            "type" => "belongs_to",
            "entity" => Customer::class,
            "column" => "customer", // Defaults to "customer_id" if not specified
        ],
        ...
    ];
    ...
}

$order = Order::getById(1);
$customer = $order->customer; // Lazy loads the Customer entity
```

#### `has_many` - One-to-Many

```php
class Order extends Entity {
    ...
    protected static array $dataMapping = [
        ...
        "items" => [
            "type" => "has_many",
            "entity" => OrderItem::class,
            "column" => "order", // The key in OrderItem that links back
            "cascade_delete" => true,
        ],
        ...
    ];
    ...
}

$order = Order::getById(1);
$items = $order->items; // Lazy loads a Collection of OrderItem entities
```

#### `has_one` - One-to-One

```php
class Order extends Entity {
    ...
    protected static array $dataMapping = [
        ...
        "payment" => [
            "type" => "has_one",
            "entity" => Payment::class,
            "column" => "order",
            "cascade_delete" => true,
        ],
        ...
    ];
    ...
}

$order = Order::getById(1);
$payment = $order->payment; // Lazy loads the Payment entity
```

## Support

If you found this library interesting or useful please spread the word about this library: share on your socials, star on GitHub, etc.

If you find any issues or have any feature requests, you can open a [issue](https://github.com/jahidulpabelislam/orm/issues) or email [me @ jahidulpabelislam.com](mailto:me@jahidulpabelislam.com) :smirk:.

## Authors

- [Jahidul Pabel Islam](https://jahidulpabelislam.com/) [<me@jahidulpabelislam.com>](mailto:me@jahidulpabelislam.com)

## Licence

This module is licensed under the General Public Licence - see the [licence](LICENSE.md) file for details.
