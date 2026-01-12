<?php

declare(strict_types=1);

namespace JPI\ORM\Tests\Fixtures;

class ChildEntity extends AbstractEntity {

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
}
