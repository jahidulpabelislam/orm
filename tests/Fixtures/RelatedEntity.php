<?php

declare(strict_types=1);

namespace JPI\ORM\Tests\Fixtures;

class RelatedEntity extends AbstractEntity {

    protected static string $table = "related_table";

    protected static array $dataMapping = [
        "title" => [
            "type" => "string",
        ],
    ];
}
