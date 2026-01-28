<?php

declare(strict_types=1);

namespace JPI\ORM\Tests;

use JPI\Database;
use JPI\ORM\Entity\Collection as EntityCollection;
use JPI\ORM\Tests\Fixtures\ChildEntity;
use JPI\ORM\Tests\Fixtures\RelatedEntity;
use JPI\ORM\Tests\Fixtures\TestEntityWithRelationships;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Tests for eager loading functionality.
 *
 * @covers \JPI\ORM\Entity\QueryBuilder::with
 * @covers \JPI\ORM\Entity\Collection::load
 * @covers \JPI\ORM\Entity\Collection::eagerLoadBelongsTo
 * @covers \JPI\ORM\Entity\Collection::eagerLoadHasOneOrMany
 * @covers \JPI\ORM\Entity\Collection::eagerLoadRelation
 */
final class EagerLoadingTest extends TestCase {

    private function createDatabase(): Database&MockObject {
        return $this->createMock(Database::class);
    }

    public function testWithMethodStoresRelationships(): void {
        $query = TestEntityWithRelationships::newQuery();
        $query->with('related');
        
        // Use reflection to check that eagerLoad property contains 'related'
        $reflection = new \ReflectionClass($query);
        $property = $reflection->getProperty('eagerLoad');
        $property->setAccessible(true);
        
        $this->assertContains('related', $property->getValue($query));
    }

    public function testWithMethodAcceptsMultipleRelationships(): void {
        $query = TestEntityWithRelationships::newQuery();
        $query->with('related', 'child', 'children');
        
        $reflection = new \ReflectionClass($query);
        $property = $reflection->getProperty('eagerLoad');
        $property->setAccessible(true);
        $eagerLoad = $property->getValue($query);
        
        $this->assertContains('related', $eagerLoad);
        $this->assertContains('child', $eagerLoad);
        $this->assertContains('children', $eagerLoad);
    }

    public function testWithMethodIsChainable(): void {
        $query = TestEntityWithRelationships::newQuery();
        $result = $query->with('related');
        
        $this->assertSame($query, $result);
    }

    public function testLoadMethodOnEmptyCollection(): void {
        $collection = new EntityCollection([]);
        $result = $collection->load('related');
        
        // Should return the same collection without error
        $this->assertSame($collection, $result);
        $this->assertEmpty($collection);
    }

    public function testLoadMethodIsChainable(): void {
        // Create a test entity from database row
        $entity = TestEntityWithRelationships::loadFromDatabaseRow([
            'id' => 1,
            'name' => 'Test',
            'age' => 25
        ]);
        
        $collection = new EntityCollection([$entity]);
        $result = $collection->load('related');
        
        $this->assertSame($collection, $result);
    }

    public function testEagerLoadBelongsToRelationship(): void {
        $database = $this->createDatabase();
        
        // Mock the initial query for main entity
        $database->expects($this->exactly(2))
            ->method('selectAll')
            ->willReturnOnConsecutiveCalls(
                // First call: main entity query
                [['id' => 1, 'name' => 'Main 1', 'other_related_id' => 100]],
                // Second call: eager load related entity
                [['id' => 100, 'title' => 'Related 1']]
            );
        
        TestEntityWithRelationships::setDatabase($database);
        RelatedEntity::setDatabase($database);
        
        // Fetch main entity
        $entity = TestEntityWithRelationships::loadFromDatabaseRow([
            'id' => 1,
            'name' => 'Main 1',
            'other_related_id' => 100
        ]);
        
        // Eager load the relationship
        $collection = new EntityCollection([$entity]);
        $collection->load('related');
        
        // Access the relationship
        $related = $entity->related;
        
        $this->assertInstanceOf(RelatedEntity::class, $related);
        $this->assertEquals('Related 1', $related->title);
    }

    public function testEagerLoadHasOneRelationship(): void {
        $database = $this->createDatabase();
        
        // Mock queries
        $database->expects($this->once())
            ->method('selectAll')
            ->willReturn([['id' => 1, 'title' => 'Child 1', 'parent_id' => 1]]);
        
        TestEntityWithRelationships::setDatabase($database);
        ChildEntity::setDatabase($database);
        
        // Create main entity
        $entity = TestEntityWithRelationships::loadFromDatabaseRow([
            'id' => 1,
            'name' => 'Parent 1'
        ]);
        
        // Eager load has_one relationship
        $collection = new EntityCollection([$entity]);
        $collection->load('child');
        
        $child = $entity->child;
        
        $this->assertInstanceOf(ChildEntity::class, $child);
        $this->assertEquals('Child 1', $child->title);
    }

    public function testEagerLoadHasManyRelationship(): void {
        $database = $this->createDatabase();
        
        // Mock query for children
        $database->expects($this->once())
            ->method('selectAll')
            ->willReturn([
                ['id' => 1, 'title' => 'Child 1', 'parent_id' => 1],
                ['id' => 2, 'title' => 'Child 2', 'parent_id' => 1]
            ]);
        
        TestEntityWithRelationships::setDatabase($database);
        ChildEntity::setDatabase($database);
        
        // Create main entity
        $entity = TestEntityWithRelationships::loadFromDatabaseRow([
            'id' => 1,
            'name' => 'Parent 1'
        ]);
        
        // Eager load has_many relationship
        $collection = new EntityCollection([$entity]);
        $collection->load('children');
        
        $children = $entity->children;
        
        $this->assertInstanceOf(EntityCollection::class, $children);
        $this->assertCount(2, $children);
        $this->assertEquals('Child 1', $children[0]->title);
        $this->assertEquals('Child 2', $children[1]->title);
    }

    public function testEagerLoadWithQueryBuilderWith(): void {
        $database = $this->createDatabase();
        
        // Mock queries - one for main entity, one for eager loaded relationship
        $database->expects($this->exactly(2))
            ->method('selectAll')
            ->willReturnOnConsecutiveCalls(
                [['id' => 1, 'name' => 'Main 1', 'other_related_id' => 100]],
                [['id' => 100, 'title' => 'Related 1']]
            );
        
        TestEntityWithRelationships::setDatabase($database);
        RelatedEntity::setDatabase($database);
        
        // Use with() method on query builder
        $entity = TestEntityWithRelationships::newQuery()
            ->with('related')
            ->select();
        
        // The relationship should already be loaded
        $related = $entity->related;
        
        $this->assertInstanceOf(RelatedEntity::class, $related);
        $this->assertEquals('Related 1', $related->title);
    }

    public function testEagerLoadMultipleRelationships(): void {
        $database = $this->createDatabase();
        
        // Mock queries
        $database->expects($this->exactly(3))
            ->method('selectAll')
            ->willReturnOnConsecutiveCalls(
                // Main entity
                [['id' => 1, 'name' => 'Parent 1', 'other_related_id' => 100]],
                // Related entity (belongs_to)
                [['id' => 100, 'title' => 'Related 1']],
                // Children entities (has_many)
                [
                    ['id' => 1, 'title' => 'Child 1', 'parent_id' => 1],
                    ['id' => 2, 'title' => 'Child 2', 'parent_id' => 1]
                ]
            );
        
        TestEntityWithRelationships::setDatabase($database);
        RelatedEntity::setDatabase($database);
        ChildEntity::setDatabase($database);
        
        // Eager load multiple relationships
        $entity = TestEntityWithRelationships::newQuery()
            ->with('related', 'children')
            ->select();
        
        $this->assertInstanceOf(RelatedEntity::class, $entity->related);
        $this->assertEquals('Related 1', $entity->related->title);
        
        $this->assertInstanceOf(EntityCollection::class, $entity->children);
        $this->assertCount(2, $entity->children);
    }

    public function testEagerLoadOnCollectionWithMultipleEntities(): void {
        $database = $this->createDatabase();
        
        // Mock query for eager loading related entities
        $database->expects($this->once())
            ->method('selectAll')
            ->willReturn([
                ['id' => 100, 'title' => 'Related 1'],
                ['id' => 200, 'title' => 'Related 2']
            ]);
        
        TestEntityWithRelationships::setDatabase($database);
        RelatedEntity::setDatabase($database);
        
        // Create multiple main entities
        $entity1 = TestEntityWithRelationships::loadFromDatabaseRow([
            'id' => 1,
            'name' => 'Main 1',
            'other_related_id' => 100
        ]);
        $entity2 = TestEntityWithRelationships::loadFromDatabaseRow([
            'id' => 2,
            'name' => 'Main 2',
            'other_related_id' => 200
        ]);
        
        $entities = new EntityCollection([$entity1, $entity2]);
        
        // Eager load relationships
        $entities->load('related');
        
        // Verify all relationships are loaded
        $this->assertEquals('Related 1', $entities[0]->related->title);
        $this->assertEquals('Related 2', $entities[1]->related->title);
    }

    public function testAvoidReloadingAlreadyLoadedRelationships(): void {
        $database = $this->createDatabase();
        
        // Should only be called once for the initial load, not again on second load()
        $database->expects($this->once())
            ->method('selectAll')
            ->willReturn([['id' => 100, 'title' => 'Related 1']]);
        
        TestEntityWithRelationships::setDatabase($database);
        RelatedEntity::setDatabase($database);
        
        $entity = TestEntityWithRelationships::loadFromDatabaseRow([
            'id' => 1,
            'name' => 'Main 1',
            'other_related_id' => 100
        ]);
        
        // Load the relationship first time
        $collection = new EntityCollection([$entity]);
        $collection->load('related');
        $related1 = $entity->related;
        
        // Try to eager load again - should not trigger another database query
        $collection->load('related');
        $related2 = $entity->related;
        
        // Should still be the same instance
        $this->assertSame($related1, $related2);
    }
}

