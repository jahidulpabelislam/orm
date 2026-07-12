<?php

declare(strict_types=1);

namespace JPI\ORM\Tests\fixtures;

use JPI\Database;
use JPI\ORM\Entity;

abstract class AbstractEntity extends Entity {

    private static ?Database $database = null;

    public static function setDatabase(Database $database): void {
        self::$database = $database;
    }

    public static function getDatabase(): Database {
        if (self::$database === null) {
            return new Database("sqlite::memory:");
        }
        return self::$database;
    }
}
