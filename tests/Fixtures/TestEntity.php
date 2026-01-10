<?php

declare(strict_types=1);

namespace JPI\ORM\Tests\Fixtures;

use JPI\Database;
use JPI\ORM\Entity;

class TestEntity extends Entity {

    protected static string $table = "test_table";

    protected static array $dataMapping = [
        "name" => [
            "type" => "string",
        ],
        "age" => [
            "type" => "int",
        ],
        "price" => [
            "type" => "float",
        ],
        "tags" => [
            "type" => "array",
            "separator" => ",",
        ],
        "created_at" => [
            "type" => "date_time",
        ],
        "birth_date" => [
            "type" => "date",
        ],
    ];

    public static function getDatabase(): Database {
        // Return a mock database for testing
        return new Database("sqlite::memory:");
    }
}
