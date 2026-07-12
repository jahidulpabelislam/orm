<?php

declare(strict_types=1);

namespace JPI\ORM\Tests\Unit;

use JPI\Database;
use JPI\ORM\Entity\Collection as EntityCollection;
use JPI\ORM\Tests\fixtures\RelatedEntity;
use JPI\ORM\Tests\fixtures\TestEntity;
use JPI\ORM\Tests\fixtures\TestEntityWithPrefix;
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
        $queryBuilder = TestEntity::newQuery()->where("other_related_id", "=", $entity);

        $this->assertSame(["other_related_id" => 123], $queryBuilder->getParams());
    }

    public function testWhereWithEntityCollection(): void {
        $entity1 = RelatedEntity::loadFromDatabaseRow(["id" => 10]);
        $entity2 = RelatedEntity::loadFromDatabaseRow(["id" => 20]);
        $entity3 = RelatedEntity::loadFromDatabaseRow(["id" => 30]);
        $collection = new EntityCollection([$entity1, $entity2, $entity3]);

        $queryBuilder = TestEntity::newQuery()->where("other_related_id", "IN", $collection);

        $this->assertSame(
            [
                "other_related_id_1" => 10,
                "other_related_id_2" => 20,
                "other_related_id_3" => 30,
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

    public function testColumnAppliesPrefix(): void {
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
                $this->equalTo("SELECT COUNT(prefix_age) as count
FROM prefixed_table
LIMIT 1;"),
                $this->equalTo([])
            )
            ->willReturn(["count" => 2])
        ;

        TestEntityWithPrefix::setDatabase($database);
        TestEntityWithPrefix::newQuery()->count("age");
    }

    public function testAndConditionAppliesPrefix(): void {
        $database = $this->createDatabase();
        $database->expects($this->once())
            ->method("selectAll")
            ->with(
                $this->equalTo("SELECT *
FROM prefixed_table
WHERE (prefix_name = :prefix_name AND prefix_age = :prefix_age)
ORDER BY prefix_id ASC;"),
                $this->equalTo([
                    "prefix_name" => "Test",
                    "prefix_age" => 1,
                ])
            )
            ->willReturn([])
        ;

        TestEntityWithPrefix::setDatabase($database);
        $query = TestEntityWithPrefix::newQuery();
        $query->where(
            $query->newAndCondition()
                ->where("name", "=", "Test")
                ->where("age", "=", 1)
        )
            ->select();
    }

    public function testOrConditionAppliesPrefix(): void {
        $database = $this->createDatabase();
        $database->expects($this->once())
            ->method("selectAll")
            ->with(
                $this->equalTo("SELECT *
FROM prefixed_table
WHERE (prefix_name = :prefix_name OR prefix_age = :prefix_age)
ORDER BY prefix_id ASC;"),
                $this->equalTo([
                    "prefix_name" => "Test",
                    "prefix_age" => 1,
                ])
            )
            ->willReturn([])
        ;

        TestEntityWithPrefix::setDatabase($database);
        $query = TestEntityWithPrefix::newQuery();
        $query->where(
            $query->newOrCondition()
                ->where("name", "=", "Test")
                ->where("age", "=", 1)
        )
            ->select();
    }

    public function testInsertAppliesPrefix(): void {
        $database = $this->createDatabase();
        $database->expects($this->once())
            ->method("exec")
            ->with(
                $this->equalTo("INSERT INTO prefixed_table
(prefix_name,prefix_age)
VALUES (:prefix_name__row1,:prefix_age__row1);"),
                $this->equalTo([
                    "prefix_name__row1" => "Test",
                    "prefix_age__row1" => 1,
                ])
            )
            ->willReturn(1)
        ;

        TestEntityWithPrefix::setDatabase($database);
        TestEntityWithPrefix::newQuery()->insert([
            "name" => "Test",
            "age" => 1,
        ]);
    }

    public function testUpdateAppliesPrefix(): void {
        $database = $this->createDatabase();
        $database->expects($this->once())
            ->method("exec")
            ->with(
                $this->equalTo("UPDATE prefixed_table
SET prefix_name = :prefix_name,prefix_age = :prefix_age;"),
                $this->equalTo([
                    "prefix_name" => "Updated",
                    "prefix_age" => 2,
                ])
            )
            ->willReturn(1)
        ;

        TestEntityWithPrefix::setDatabase($database);
        TestEntityWithPrefix::newQuery()->update([
            "name" => "Updated",
            "age" => 2,
        ]);
    }
}
