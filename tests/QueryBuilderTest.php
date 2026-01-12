<?php

declare(strict_types=1);

namespace JPI\ORM\Tests;

use JPI\ORM\Entity\Collection as EntityCollection;
use JPI\ORM\Tests\Fixtures\RelatedEntity;
use JPI\ORM\Tests\Fixtures\TestEntity;
use JPI\ORM\Tests\Fixtures\TestEntityWithPrefix;
use PHPUnit\Framework\TestCase;

class QueryBuilderTest extends TestCase {

    public function testWhereWithEntityInstanceConvertsToId(): void {
        $entity = RelatedEntity::loadFromDatabaseRow(["id" => 123]);

        $queryBuilder = TestEntity::newQuery();
        $queryBuilder->where("related_id", "=", $entity);

        $parameters = $queryBuilder->getParams();

        $this->assertEquals(123, $parameters["related_id"]);
    }

    public function testWhereWithEntityCollectionConvertsToArrayOfIds(): void {
        $entity1 = RelatedEntity::loadFromDatabaseRow(["id" => 10]);
        $entity2 = RelatedEntity::loadFromDatabaseRow(["id" => 20]);
        $entity3 = RelatedEntity::loadFromDatabaseRow(["id" => 30]);

        $collection = new EntityCollection([$entity1, $entity2, $entity3]);

        $queryBuilder = TestEntity::newQuery();
        $queryBuilder->where("related_id", "IN", $collection);

        $parameters = $queryBuilder->getParams();

        // When using IN with an array, parameters are stored with indexed keys
        $this->assertEquals(10, $parameters["related_id_1"]);
        $this->assertEquals(20, $parameters["related_id_2"]);
        $this->assertEquals(30, $parameters["related_id_3"]);
    }

    public function testWhereAppliesColumnPrefix(): void {
        $queryBuilder = TestEntityWithPrefix::newQuery();
        $queryBuilder->where("name", "=", "Test");

        $selectQuery = $queryBuilder->getSelectQuery();

        // The query should use the prefixed column name in WHERE clause
        $this->assertMatchesRegularExpression('/WHERE\s+prefix_name\s*=\s*:prefix_name/', $selectQuery);
    }

    public function testWhereWithNonColumnNameDoesNotApplyPrefix(): void {
        $queryBuilder = TestEntityWithPrefix::newQuery();

        // When using a full SQL expression, prefix should not be applied
        $queryBuilder->where("custom_expression = :value");

        $selectQuery = $queryBuilder->getSelectQuery();

        // The custom expression should be used as-is without prefix
        $this->assertMatchesRegularExpression('/WHERE\s+custom_expression\s*=\s*:value/', $selectQuery);
    }

    public function testColumnMethodAppliesPrefix(): void {
        $queryBuilder = TestEntityWithPrefix::newQuery();
        $queryBuilder->column("name");

        $selectQuery = $queryBuilder->getSelectQuery();

        // The SELECT clause should use the prefixed column name
        $this->assertMatchesRegularExpression('/SELECT\s+prefix_name\s+FROM/', $selectQuery);
    }

    public function testOrderByAppliesPrefix(): void {
        $queryBuilder = TestEntityWithPrefix::newQuery();
        $queryBuilder->orderBy("name");

        $selectQuery = $queryBuilder->getSelectQuery();

        // The ORDER BY clause should use the prefixed column name
        $this->assertMatchesRegularExpression('/ORDER BY\s+prefix_name(\s+ASC|\s+DESC)?/', $selectQuery);
    }
}
