<?php

declare(strict_types=1);

namespace JPI\ORM\Tests;

use JPI\ORM\Entity\Collection as EntityCollection;
use JPI\ORM\Tests\Fixtures\RelatedEntity;
use JPI\ORM\Tests\Fixtures\TestEntity;
use JPI\ORM\Tests\Fixtures\TestEntityWithPrefix;
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

    public function testWhereWithEntityInstanceConvertsToId(): void {
        $entity = RelatedEntity::loadFromDatabaseRow(["id" => 123]);
        $queryBuilder = TestEntity::newQuery()->where("related_id", "=", $entity);

        $this->assertEquals(123, $queryBuilder->getParams()["related_id"]);
    }

    public function testWhereWithEntityCollectionConvertsToArrayOfIds(): void {
        $entity1 = RelatedEntity::loadFromDatabaseRow(["id" => 10]);
        $entity2 = RelatedEntity::loadFromDatabaseRow(["id" => 20]);
        $entity3 = RelatedEntity::loadFromDatabaseRow(["id" => 30]);
        $collection = new EntityCollection([$entity1, $entity2, $entity3]);

        $queryBuilder = TestEntity::newQuery()->where("related_id", "IN", $collection);
        $parameters = $queryBuilder->getParams();

        // When using IN with an array, parameters are stored with indexed keys
        $this->assertEquals(10, $parameters["related_id_1"]);
        $this->assertEquals(20, $parameters["related_id_2"]);
        $this->assertEquals(30, $parameters["related_id_3"]);
    }

    public function testWhereAppliesColumnPrefix(): void {
        $queryBuilder = TestEntityWithPrefix::newQuery()->where("name", "=", "Test");

        $this->assertSame(
            "SELECT *
FROM prefixed_table
WHERE prefix_name = :prefix_name
ORDER BY prefix_id ASC;",
            $queryBuilder->getSelectQuery()
        );
    }

    public function testWhereWithNonColumnNameDoesNotApplyPrefix(): void {
        // When using a full SQL expression, prefix should not be applied
        $queryBuilder = TestEntityWithPrefix::newQuery()->where("custom_expression = :value");

        $this->assertSame(
            "SELECT *
FROM prefixed_table
WHERE custom_expression = :value
ORDER BY prefix_id ASC;",
            $queryBuilder->getSelectQuery()
        );
    }

    public function testColumnMethodAppliesPrefix(): void {
        $queryBuilder = TestEntityWithPrefix::newQuery()->column("name");

        $this->assertSame(
            "SELECT prefix_name
FROM prefixed_table
ORDER BY prefix_id ASC;",
            $queryBuilder->getSelectQuery()
        );
    }

    public function testOrderByAppliesPrefix(): void {
        $queryBuilder = TestEntityWithPrefix::newQuery()->orderBy("name");

        $this->assertSame(
            "SELECT *
FROM prefixed_table
ORDER BY prefix_name ASC;",
            $queryBuilder->getSelectQuery()
        );
    }
}
