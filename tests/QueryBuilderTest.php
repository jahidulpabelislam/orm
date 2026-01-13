<?php

declare(strict_types=1);

namespace JPI\ORM\Tests;

use JPI\Database;
use JPI\ORM\Entity\Collection as EntityCollection;
use JPI\ORM\Tests\Fixtures\RelatedEntity;
use JPI\ORM\Tests\Fixtures\TestEntity;
use JPI\ORM\Tests\Fixtures\TestEntityWithPrefix;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Tests for Entity QueryBuilder functionality.
 *
 * This test class validates ORM-specific QueryBuilder behavior:
 * - Entity and EntityCollection conversion to IDs in WHERE clauses
 * - Automatic column prefix application to queries (WHERE, SELECT, ORDER BY)
 * - Custom SQL expressions passed through without prefix modification
 */
class QueryBuilderTest extends TestCase {

    private function createDatabase(): Database&MockObject {
        return $this->createMock(Database::class);
    }

    public function testWhereWithEntity(): void {
        $entity = RelatedEntity::loadFromDatabaseRow(["id" => 123]);
        $queryBuilder = TestEntity::newQuery()->where("related_id", "=", $entity);

        $this->assertEquals(["related_id" => 123], $queryBuilder->getParams());
    }

    public function testWhereWithEntityCollection(): void {
        $entity1 = RelatedEntity::loadFromDatabaseRow(["id" => 10]);
        $entity2 = RelatedEntity::loadFromDatabaseRow(["id" => 20]);
        $entity3 = RelatedEntity::loadFromDatabaseRow(["id" => 30]);
        $collection = new EntityCollection([$entity1, $entity2, $entity3]);

        $queryBuilder = TestEntity::newQuery()->where("related_id", "IN", $collection);

        $this->assertEquals(
            [
                "related_id_1" => 10,
                "related_id_2" => 20,
                "related_id_3" => 30,
            ],
            $queryBuilder->getParams()
        );
    }

    public function testWhereAppliesPrefix(): void {
        $database = $this->createDatabase();
        $database->expects($this->once())
            ->method("selectAll")
            ->with(
                $this->equalTo("SELECT *
FROM prefixed_table
WHERE prefix_name = :prefix_name
ORDER BY prefix_id ASC;"),
                $this->equalTo([
                    "prefix_name" => "Test",
                ])
            )
            ->willReturn([])
        ;

        TestEntityWithPrefix::setDatabase($database);
        TestEntityWithPrefix::newQuery()->where("name", "=", "Test")->select();
    }

    public function testWhereWithNonColumnNameDoesNotApplyPrefix(): void {
        $database = $this->createDatabase();
        $database->expects($this->once())
            ->method("selectAll")
            ->with(
                $this->equalTo("SELECT *
FROM prefixed_table
WHERE custom_expression = :value
ORDER BY prefix_id ASC;"),
                $this->equalTo([])
            )
            ->willReturn([])
        ;

        TestEntityWithPrefix::setDatabase($database);
        TestEntityWithPrefix::newQuery()->where("custom_expression = :value")->select();
    }

    public function testColumnMethodAppliesPrefix(): void {
        $database = $this->createDatabase();
        $database->expects($this->once())
            ->method("selectAll")
            ->with(
                $this->equalTo("SELECT prefix_name
FROM prefixed_table
ORDER BY prefix_id ASC;"),
                $this->equalTo([])
            )
            ->willReturn([])
        ;

        TestEntityWithPrefix::setDatabase($database);
        TestEntityWithPrefix::newQuery()->column("name")->select();
    }

    public function testOrderByAppliesPrefix(): void {
        $database = $this->createDatabase();
        $database->expects($this->once())
            ->method("selectAll")
            ->with(
                $this->equalTo("SELECT *
FROM prefixed_table
ORDER BY prefix_name ASC;"),
                $this->equalTo([])
            )
            ->willReturn([])
        ;

        TestEntityWithPrefix::setDatabase($database);
        TestEntityWithPrefix::newQuery()->orderBy("name")->select();
    }

    public function testCountAppliesPrefix(): void {
        $database = $this->createDatabase();
        $database->expects($this->once())
            ->method("selectFirst")
            ->with(
                $this->equalTo("SELECT COUNT(prefix_status) as count
FROM prefixed_table
LIMIT 1;"),
                $this->equalTo([])
            )
            ->willReturn([])
        ;

        TestEntityWithPrefix::setDatabase($database);
        TestEntityWithPrefix::newQuery()->count("status");
    }
}
