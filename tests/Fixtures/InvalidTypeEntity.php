<?php

declare(strict_types=1);

namespace JPI\ORM\Tests\Fixtures;

use JPI\Database;
use JPI\ORM\Entity;

class InvalidTypeEntity extends Entity {

    protected static string $table = "invalid_table";

    protected static array $dataMapping = [
        "name" => [
            "type" => "invalid_type",
        ],
    ];

    public static function getDatabase(): Database {
        return new Database("sqlite::memory:");
    }
}
