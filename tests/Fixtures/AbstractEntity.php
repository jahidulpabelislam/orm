<?php

declare(strict_types=1);

namespace JPI\ORM\Tests\Fixtures;

use JPI\ORM\Entity;

abstract class AbstractEntity extends Entity {

    private static ?\JPI\Database $database = null;

    public static function setDatabase(\JPI\Database $database): void {
        self::$database = $database;
    }

    public static function getDatabase(): \JPI\Database {
        if (self::$database === null) {
            return new \JPI\Database("sqlite::memory:");
        }
        return self::$database;
    }
}
