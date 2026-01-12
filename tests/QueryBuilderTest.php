<?php

declare(strict_types=1);

namespace JPI\ORM\Tests;

use JPI\ORM\Entity\Collection as EntityCollection;
use JPI\ORM\Tests\Fixtures\RelatedEntity;
use JPI\ORM\Tests\Fixtures\TestEntity;
use JPI\ORM\Tests\Fixtures\TestEntityWithPrefix;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class QueryBuilderTest extends TestCase {

    public function testWhereWithEntityInstanceConvertsToId(): void {
        $entity = RelatedEntity::loadFromDatabaseRow(["id" => 123]);
        
        $queryBuilder = TestEntity::newQuery();
        $queryBuilder->where("related_id", "=", $entity);
        
        // Build the SQL to trigger parameter binding
        $reflection = new ReflectionClass($queryBuilder);
        
        // Check the where clauses contain the entity ID
        $whereProperty = $reflection->getProperty('where');
        $whereProperty->setAccessible(true);
        $whereClauses = $whereProperty->getValue($queryBuilder);
        
        $this->assertCount(1, $whereClauses);
        
        // Get parameters - they're in the params property
        $parametersProperty = $reflection->getProperty('params');
        $parametersProperty->setAccessible(true);
        $parameters = $parametersProperty->getValue($queryBuilder);
        
        $this->assertArrayHasKey("related_id", $parameters);
        $this->assertEquals(123, $parameters["related_id"]);
    }

    public function testWhereWithEntityCollectionConvertsToArrayOfIds(): void {
        $entity1 = RelatedEntity::loadFromDatabaseRow(["id" => 10]);
        $entity2 = RelatedEntity::loadFromDatabaseRow(["id" => 20]);
        $entity3 = RelatedEntity::loadFromDatabaseRow(["id" => 30]);
        
        $collection = new EntityCollection([$entity1, $entity2, $entity3]);
        
        $queryBuilder = TestEntity::newQuery();
        $queryBuilder->where("related_id", "IN", $collection);
        
        // Get parameters from params property
        $reflection = new ReflectionClass($queryBuilder);
        $parametersProperty = $reflection->getProperty('params');
        $parametersProperty->setAccessible(true);
        $parameters = $parametersProperty->getValue($queryBuilder);
        
        // When using IN with an array, parameters are stored with indexed keys
        $this->assertArrayHasKey("related_id_1", $parameters);
        $this->assertArrayHasKey("related_id_2", $parameters);
        $this->assertArrayHasKey("related_id_3", $parameters);
        $this->assertEquals(10, $parameters["related_id_1"]);
        $this->assertEquals(20, $parameters["related_id_2"]);
        $this->assertEquals(30, $parameters["related_id_3"]);
    }

    public function testWhereAppliesColumnPrefix(): void {
        $queryBuilder = TestEntityWithPrefix::newQuery();
        $queryBuilder->where("name", "=", "Test");
        
        // Get the where clauses to check if prefix was applied
        $reflection = new ReflectionClass($queryBuilder);
        $property = $reflection->getProperty('where');
        $property->setAccessible(true);
        $whereClauses = $property->getValue($queryBuilder);
        
        // The where clause should contain the prefixed column name
        $this->assertCount(1, $whereClauses);
        $whereString = (string)$whereClauses[0];
        $this->assertStringContainsString("prefix_name", $whereString);
    }

    public function testWhereWithNonColumnNameDoesNotApplyPrefix(): void {
        $queryBuilder = TestEntityWithPrefix::newQuery();
        
        // When using a full SQL expression, prefix should not be applied
        $queryBuilder->where("custom_expression = :value");
        
        $reflection = new ReflectionClass($queryBuilder);
        $property = $reflection->getProperty('where');
        $property->setAccessible(true);
        $whereClauses = $property->getValue($queryBuilder);
        
        $this->assertCount(1, $whereClauses);
        $whereString = (string)$whereClauses[0];
        $this->assertStringContainsString("custom_expression", $whereString);
    }

    public function testColumnMethodAppliesPrefix(): void {
        $queryBuilder = TestEntityWithPrefix::newQuery();
        $queryBuilder->column("name");
        
        $reflection = new ReflectionClass($queryBuilder);
        $property = $reflection->getProperty('columns');
        $property->setAccessible(true);
        $columns = $property->getValue($queryBuilder);
        
        // The column should be prefixed
        $this->assertContains("prefix_name", $columns);
    }

    public function testOrderByAppliesPrefix(): void {
        $queryBuilder = TestEntityWithPrefix::newQuery();
        $queryBuilder->orderBy("name");
        
        $reflection = new ReflectionClass($queryBuilder);
        $property = $reflection->getProperty('orderBy');
        $property->setAccessible(true);
        $orderBy = $property->getValue($queryBuilder);
        
        // The order by is an object, convert to string to check
        $orderByString = (string)$orderBy;
        $this->assertStringContainsString("prefix_name", $orderByString);
    }
}
