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

## Usage

You will need to extend `\JPI\ORM\Entity` and then define the following:

### Required Properties and Methods

#### `getDatabase(): \JPI\Database`

This method must be implemented to provide the database connection for this entity. `\JPI\Database` is just an extension of `PDO` - you can find out more [here](https://packagist.org/packages/jpi/database).

#### `$table`

The database table name for this entity.

#### `$dataMapping`

This array defines the structure of your entity and maps to your database columns. Each key is a property name and the value is an array with:

- `type` (required): One of: `string`, `int`, `float`, `array`, `date`, `date_time`, `belongs_to`, `has_many`, `has_one`
- `default_value`: Default value for the property
- `entity`: The related entity class name (required for relationship types)
- `column`: The foreign key column name for `belongs_to` (defaults to `{property}_id`)
- `cascade_delete`: Whether to delete related entities when this entity is deleted

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

#### `$columnPrefix`

Some database designers like to prefix their table columns. For example, the `users` table might have columns like `user_id` & `user_name` instead of `id` & `name`. Set this property to add that prefix automatically.

Note: the first underscore is required.

#### `$arrayColumnSeparator`

When storing arrays in a database column as a delimited string, this defines the separator. Default is `","`.

#### `$defaultOrderByColumn`

The default column to order results by when using `select()`. Default is `"id"`.

#### `$defaultOrderByASC`

Whether the default ordering should be ascending. Default is `true`.

### Complete Example

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

### Available Methods

#### Creating and Saving Entities

**`factory(?array $data = null): static`** - Create a new entity instance.

```php
$user = User::factory([
    "name" => "John Doe",
    "email" => "john@example.com",
    "age" => 30,
]);
```

**`save(): bool`** - Save (insert or update) the entity to the database.

```php
$user = User::factory(["name" => "John Doe"]);
$user->save(); // Inserts the user

$user->name = "Jane Doe";
$user->save(); // Updates the user
```

**`insert(array $data): static`** - Create and save an entity in one call.

```php
$user = User::insert([
    "name" => "John Doe",
    "email" => "john@example.com",
]);
```

#### Retrieving Entities

**`getById(int $id): ?static`** - Get an entity by its ID.

```php
$user = User::getById(1);
```

**`newQuery(): QueryBuilder`** - Get a query builder instance for advanced queries. See [Query Builder](#query-builder) section for examples.

**`reload(): void`** - Reload the entity from the database.

#### Deleting Entities

**`delete(): bool`** - Delete the entity from the database.

```php
$user = User::getById(1);
$user->delete();
```

#### Utility Methods

**`isLoaded(): bool`** - Check if the entity has been loaded from or saved to the database.

```php
if ($user->isLoaded()) {
    // Entity exists in database
}
```

**`isDeleted(): bool`** - Check if the entity has been deleted.

```php
if ($user->isDeleted()) {
    // Entity was deleted
}
```

**`toArray(int $depth = 1): array`** - Convert the entity to an array.

### Query Builder

The query builder (accessed via `newQuery()`) provides a fluent interface for building database queries. It uses `\JPI\Database\Query\Builder` from [jpi/query](https://packagist.org/packages/jpi/query).

#### Common Query Methods

```php
// Select all users
$users = User::newQuery()->select();

// Select with conditions
$adults = User::newQuery()
    ->where("age", ">=", 18)
    ->select();

// Select with multiple conditions
$users = User::newQuery()
    ->where("age", ">", 18)
    ->where("email", "LIKE", "%@example.com")
    ->select();

// Order results
$users = User::newQuery()
    ->orderBy("name", true) // true = ascending
    ->select();

// Limit results
$users = User::newQuery()
    ->limit(10)
    ->select();

// Pagination
$users = User::newQuery()
    ->paginate(1, 20); // page 1, 20 per page

// Count
$count = User::newQuery()
    ->where("age", ">", 18)
    ->count();

// Update records
$affected = User::newQuery()
    ->where("age", "<", 18)
    ->update(["status" => "minor"]);

// Delete records
$affected = User::newQuery()
    ->where("status", "=", "inactive")
    ->delete();
```

### Relationships

The ORM supports three types of relationships:

#### `belongs_to` - Many-to-One

```php
class Post extends Entity {
    protected static array $dataMapping = [
        "title" => ["type" => "string"],
        "author" => [
            "type" => "belongs_to",
            "entity" => User::class,
            "column" => "user_id", // Defaults to "author_id" if not specified
        ],
    ];
}

$post = Post::getById(1);
$author = $post->author; // Lazy loads the User entity
```

#### `has_many` - One-to-Many

```php
class User extends Entity {
    protected static array $dataMapping = [
        "name" => ["type" => "string"],
        "posts" => [
            "type" => "has_many",
            "entity" => Post::class,
            "column" => "author", // The property name in Post that links back
            "cascade_delete" => true, // Delete posts when user is deleted
        ],
    ];
}

$user = User::getById(1);
$posts = $user->posts; // Lazy loads a Collection of Post entities
```

#### `has_one` - One-to-One

```php
class User extends Entity {
    protected static array $dataMapping = [
        "name" => ["type" => "string"],
        "profile" => [
            "type" => "has_one",
            "entity" => UserProfile::class,
            "column" => "user",
            "cascade_delete" => true,
        ],
    ];
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
