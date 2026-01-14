<?php

declare(strict_types=1);

namespace JPI\ORM\Tests\Fixtures;

final class InvalidTypeEntity extends AbstractEntity {

    protected static string $table = "invalid_table";

    protected static array $dataMapping = [
        "name" => [
            "type" => "invalid_type",
        ],
    ];
}
