<?php

declare(strict_types=1);

namespace JPI\ORM\Tests\Fixtures;

final class ProfileEntity extends AbstractEntity {

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
}
