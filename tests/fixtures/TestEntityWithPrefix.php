<?php

declare(strict_types=1);

namespace JPI\ORM\Tests\fixtures;

final class TestEntityWithPrefix extends TestEntity {

    protected static string $table = "prefixed_table";

    protected static ?string $columnPrefix = "prefix_";
}
