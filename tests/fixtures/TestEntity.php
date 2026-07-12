<?php

declare(strict_types=1);

namespace JPI\ORM\Tests\fixtures;

class TestEntity extends AbstractEntity {

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
        "birth_date" => [
            "type" => "date",
        ],
        "created_at" => [
            "type" => "date_time",
        ],
    ];
}
