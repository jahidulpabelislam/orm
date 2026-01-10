<?php

declare(strict_types=1);

namespace JPI\ORM\Tests\Fixtures;

use JPI\Database;
use JPI\ORM\Entity;

class RelatedEntity extends Entity {

    protected static string $table = "related_table";

    protected static array $dataMapping = [
        "title" => [
            "type" => "string",
        ],
    ];

    public static function getDatabase(): Database {
        return new Database("sqlite::memory:");
    }
}
