<?php

declare(strict_types=1);

namespace JPI\ORM\Tests\Fixtures;

use JPI\Database;
use JPI\ORM\Entity;

class TestEntityWithPrefix extends Entity {

    protected static string $table = "prefixed_table";

    protected static ?string $columnPrefix = "prefix_";

    protected static array $dataMapping = [
        "name" => [
            "type" => "string",
        ],
        "status" => [
            "type" => "int",
        ],
    ];

    public static function getDatabase(): Database {
        return new Database("sqlite::memory:");
    }
}
