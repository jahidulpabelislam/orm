<?php

declare(strict_types=1);

namespace JPI\ORM\Tests\Fixtures;

use JPI\Database;
use JPI\ORM\Entity;

class ChildEntity extends Entity {

    protected static string $table = "child_table";

    protected static array $dataMapping = [
        "title" => [
            "type" => "string",
        ],
        "parent" => [
            "type" => "belongs_to",
            "entity" => TestEntityWithRelationships::class,
            "column" => "parent_id",
        ],
    ];

    public static function getDatabase(): Database {
        return new Database("sqlite::memory:");
    }
}
