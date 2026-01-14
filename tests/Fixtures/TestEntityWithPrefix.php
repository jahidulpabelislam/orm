<?php

declare(strict_types=1);

namespace JPI\ORM\Tests\Fixtures;

final class TestEntityWithPrefix extends AbstractEntity {

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
}
