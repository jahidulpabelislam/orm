# ORM

[![CodeFactor](https://www.codefactor.io/repository/github/jahidulpabelislam/orm/badge)](https://www.codefactor.io/repository/github/jahidulpabelislam/orm)
[![Latest Stable Version](https://poser.pugx.org/jpi/orm/v/stable)](https://packagist.org/packages/jpi/orm)
[![Total Downloads](https://poser.pugx.org/jpi/orm/downloads)](https://packagist.org/packages/jpi/orm)
[![Latest Unstable Version](https://poser.pugx.org/jpi/orm/v/unstable)](https://packagist.org/packages/jpi/orm)
[![License](https://poser.pugx.org/jpi/orm/license)](https://packagist.org/packages/jpi/orm)
![GitHub last commit (branch)](https://img.shields.io/github/last-commit/jahidulpabelislam/orm/2.x.svg?label=last%20activity)

A super simple & lightweight ORM.

This has been kept very simple stupid (KISS), other than type errors from PHP there is no validation (so use at your own risk), it will assume you are using it correctly. So please make sure to add your own validation if using user inputs in these queries.

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

## Properties and Methods for setup

You will need to extend `\JPI\ORM\Entity` and then define the following:

#### `getDatabase(): \JPI\Database`

This method must be implemented to provide the database connection for this entity. `\JPI\Database` is just an extension of `PDO` - you can find out more [here](https://packagist.org/packages/jpi/database).

#### `$table: string`

The database table name for this entity.

#### `$dataMapping: array`

This array defines the structure of your entity and maps to your database columns. Each key is a column name and the value is an array with:

- `type` (required): One of: `string`, `int`, `float`, `array`, `date`, `date_time`, `belongs_to`, `has_one`, `has_many` 
- `default_value`
- `entity`: The related entity class name (required for relationship types)
- `column`: The foreign key column name for `belongs_to` type (defaults to `{key}_id`)
- `cascade_delete`: Whether to delete related entities when this entity is deleted (for `has_one` and `has_many` types)

```php
protected static array $dataMapping = [
    "name" => [
        "type" => "string",
        "default_value" => null,
    ],
    "email" => [
        "type" => "string",
    ],
    "age" => [
        "type" => "int",
    ],
    "created_at" => [
        "type" => "date_time",
    ],
];
```

#### `$columnPrefix: string` - optional

Some database designers like to prefix their table columns. For example, the `users` table might have columns like `user_id` & `user_name` instead of `id` & `name`. Set this property to add that prefix automatically.

Note: the first underscore is required.

#### `$arrayColumnSeparator: string` - optional

Separator for `array` type columns when stored as delimited strings. Default is `","`.

#### `$defaultOrderByColumn: string` - optional

The default column to order results by when `selecting` records and haven't specified a order. Default is `"id"`.

#### `$defaultOrderByASC: bool` - optional

Whether the default ordering should be ascending. Default is `true`.

## Complete Example

```php
...
class User extends \JPI\ORM\Entity {

    protected static string $table = "users";

    protected static array $dataMapping = [
        "name" => [
            "type" => "string",
        ],
        "email" => [
            "type" => "string",
        ],
        "age" => [
            "type" => "int",
        ],
        "created_at" => [
            "type" => "date_time",
        ],
    ];

    public static function getDatabase(): \JPI\Database {
        return new \JPI\Database("mysql:host=localhost;dbname=mydb", "username", "password");
    }

    ...
}
```

## Usage

#### Retrieving Entities

**`getById(int $id): ?static`** - Get an entity by its ID.

**`newQuery(): QueryBuilder`** - Get a query builder instance for advanced queries. See [Query Builder](#query-builder) section for examples.

#### Accessing Entity Data

You can get and set entity values using simple property access, these are the keys from `$dataMapping`. When setting the value must be value for the type defined or null.

```php
// Getting values
$user = User::getById(1);
$name = $user->name;
$email = $user->email;
$age = $user->age;

// Setting values
$user->name = "Jane Doe";
$user->email = "jane@example.com";
$user->age = 25;
```

#### Creating and Saving Entities

**`factory(?array $data = null): static`** - Create a new entity instance.

```php
$user = User::factory([
    "name" => "John Doe",
    "email" => "john@example.com",
    "age" => 30,
]);
```

**`insert(array $data): static`** - Create and save an entity in one call.

```php
$user = User::insert([
    "name" => "John Doe",
    "email" => "john@example.com",
]);
```

**`save(): bool`** - Save (insert or update) the entity to the database.

```php
$user = User::factory(["name" => "John Doe"]);
$user->save(); // Inserts the user

$user->age = 31;
$user->save(); // Updates the user
```

#### Deleting Entities

**`delete(): bool`** - Delete the entity from the database.

#### Utility Methods

**`isLoaded(): bool`** - Check if the entity has been loaded from or saved to the database.

**`isDeleted(): bool`** - Check if the entity has been deleted.

**`toArray(int $depth = 1): array`** - Convert the entity to an array.

**`reload(): void`** - Reload the entity from the database.

## Query Builder

The query builder (accessed via `newQuery()`) provides a fluent interface for building database queries. It uses `\JPI\Database\Query\Builder` from [jpi/query](https://packagist.org/packages/jpi/query), see documentation there for full details on available query methods.

## Relationships

The ORM supports three types of relationships:

#### `belongs_to` - Many-to-One

```php
class Post extends Entity {
    ...
    protected static array $dataMapping = [
        ...
        "title" => ["type" => "string"],
        "author" => [
            "type" => "belongs_to",
            "entity" => User::class,
            "column" => "user_id", // Defaults to "author_id" if not specified
        ],
        ...
    ];
    ...
}

$post = Post::getById(1);
$author = $post->author; // Lazy loads the User entity
```

#### `has_many` - One-to-Many

```php
class User extends Entity {
    ...
    protected static array $dataMapping = [
        ...
        "posts" => [
            "type" => "has_many",
            "entity" => Post::class,
            "column" => "author", // The key in Post that links back
            "cascade_delete" => true,
        ],
        ...
    ];
    ...
}

$user = User::getById(1);
$posts = $user->posts; // Lazy loads a Collection of Post entities
```

#### `has_one` - One-to-One

```php
class User extends Entity {
    ...
    protected static array $dataMapping = [
        ...
        "profile" => [
            "type" => "has_one",
            "entity" => UserProfile::class,
            "column" => "user",
            "cascade_delete" => true,
        ],
        ...
    ];
    ...
}

$user = User::getById(1);
$profile = $user->profile; // Lazy loads the UserProfile entity
```

## Support

If you found this library interesting or useful please spread the word about this library: share on your socials, star on GitHub, etc.

If you find any issues or have any feature requests, you can open a [issue](https://github.com/jahidulpabelislam/orm/issues) or email [me @ jahidulpabelislam.com](mailto:me@jahidulpabelislam.com) :smirk:.

## Authors

- [Jahidul Pabel Islam](https://jahidulpabelislam.com/) [<me@jahidulpabelislam.com>](mailto:me@jahidulpabelislam.com)

## Licence

This module is licensed under the General Public Licence - see the [licence](LICENSE.md) file for details
