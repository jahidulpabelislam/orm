<?php

declare(strict_types=1);

namespace JPI\ORM\Tests\Fixtures;

use JPI\Database;
use JPI\ORM\Entity;

class TestEntityWithRelationships extends Entity {

    protected static string $table = "main_table";

    protected static array $dataMapping = [
        "name" => [
            "type" => "string",
        ],
        "related" => [
            "type" => "belongs_to",
            "entity" => RelatedEntity::class,
            "column" => "related_id",
        ],
        "children" => [
            "type" => "has_many",
            "entity" => ChildEntity::class,
            "column" => "parent",
        ],
        "profile" => [
            "type" => "has_one",
            "entity" => ProfileEntity::class,
            "column" => "owner",
        ],
    ];

    public static function getDatabase(): Database {
        return new Database("sqlite::memory:");
    }
}
