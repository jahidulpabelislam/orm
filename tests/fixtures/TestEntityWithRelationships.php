<?php

declare(strict_types=1);

namespace JPI\ORM\Tests\fixtures;

final class TestEntityWithRelationships extends AbstractEntity {

    protected static string $table = "main_table";

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
        "birth_date" => [
            "type" => "date",
        ],
        "related" => [
            "type" => "belongs_to",
            "entity" => RelatedEntity::class,
            "column" => "other_related_id",
        ],
        "child" => [
            "type" => "has_one",
            "entity" => ChildEntity::class,
            "column" => "parent",
        ],
        "children" => [
            "type" => "has_many",
            "entity" => ChildEntity::class,
            "column" => "parent",
        ],
        "created_at" => [
            "type" => "date_time",
        ],
    ];
}
