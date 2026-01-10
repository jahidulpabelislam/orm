<?php

declare(strict_types=1);

namespace JPI\ORM\Tests\Fixtures;

use JPI\Database;
use JPI\ORM\Entity;

class ProfileEntity extends Entity {

    protected static string $table = "profile_table";

    protected static array $dataMapping = [
        "bio" => [
            "type" => "string",
        ],
        "owner" => [
            "type" => "belongs_to",
            "entity" => TestEntityWithRelationships::class,
            "column" => "owner_id",
        ],
    ];

    public static function getDatabase(): Database {
        return new Database("sqlite::memory:");
    }
}
