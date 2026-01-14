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
 *
 * @covers \JPI\ORM\Entity\QueryBuilder
 * @covers \JPI\ORM\Entity\QueryBuilder\WhereableTrait
 * @covers \JPI\ORM\Entity\QueryBuilder\Clause\Where
 * @covers \JPI\ORM\Entity\QueryBuilder\Clause\Where\AndCondition
 * @covers \JPI\ORM\Entity\QueryBuilder\Clause\Where\OrCondition
 */
final class QueryBuilderTest extends TestCase {

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
            ->willReturn(["count" => 2])
        ;

        TestEntityWithPrefix::setDatabase($database);
        TestEntityWithPrefix::newQuery()->count("status");
    }

    public function testAndConditionAppliesPrefix(): void {
        $database = $this->createDatabase();
        $database->expects($this->once())
            ->method("selectAll")
            ->with(
                $this->equalTo("SELECT *
FROM prefixed_table
WHERE (prefix_name = :prefix_name AND prefix_status = :prefix_status)
ORDER BY prefix_id ASC;"),
                $this->equalTo([
                    "prefix_name" => "Test",
                    "prefix_status" => 1,
                ])
            )
            ->willReturn([])
        ;

        TestEntityWithPrefix::setDatabase($database);
        $query = TestEntityWithPrefix::newQuery();
        $condition = $query->newAndCondition();
        $condition->where("name", "=", "Test");
        $condition->where("status", "=", 1);
        $query->where($condition)->select();
    }

    public function testOrConditionAppliesPrefix(): void {
        $database = $this->createDatabase();
        $database->expects($this->once())
            ->method("selectAll")
            ->with(
                $this->equalTo("SELECT *
FROM prefixed_table
WHERE (prefix_name = :prefix_name OR prefix_status = :prefix_status)
ORDER BY prefix_id ASC;"),
                $this->equalTo([
                    "prefix_name" => "Test",
                    "prefix_status" => 1,
                ])
            )
            ->willReturn([])
        ;

        TestEntityWithPrefix::setDatabase($database);
        $query = TestEntityWithPrefix::newQuery();
        $condition = $query->newOrCondition();
        $condition->where("name", "=", "Test");
        $condition->where("status", "=", 1);
        $query->where($condition)->select();
    }

    public function testInsertAppliesPrefix(): void {
        $database = $this->createDatabase();
        $database->expects($this->once())
            ->method("exec")
            ->with(
                $this->equalTo("INSERT INTO prefixed_table
(prefix_name,prefix_status)
VALUES (:prefix_name__row1,:prefix_status__row1);"),
                $this->equalTo([
                    "prefix_name__row1" => "Test",
                    "prefix_status__row1" => 1,
                ])
            )
            ->willReturn(1)
        ;

        TestEntityWithPrefix::setDatabase($database);
        TestEntityWithPrefix::newQuery()->insert([
            "name" => "Test",
            "status" => 1,
        ]);
    }

    public function testUpdateAppliesPrefix(): void {
        $database = $this->createDatabase();
        $database->expects($this->once())
            ->method("exec")
            ->with(
                $this->equalTo("UPDATE prefixed_table
SET prefix_name = :prefix_name,prefix_status = :prefix_status;"),
                $this->equalTo([
                    "prefix_name" => "Updated",
                    "prefix_status" => 2,
                ])
            )
            ->willReturn(1)
        ;

        TestEntityWithPrefix::setDatabase($database);
        TestEntityWithPrefix::newQuery()->update([
            "name" => "Updated",
            "status" => 2,
        ]);
    }
}
