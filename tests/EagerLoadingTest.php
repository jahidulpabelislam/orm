<?php

declare(strict_types=1);

namespace JPI\ORM\Tests;

use JPI\Database;
use JPI\ORM\Entity\Collection as EntityCollection;
use JPI\ORM\Tests\Fixtures\AbstractEntity;
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
        $database = $this->createMock(Database::class);
        AbstractEntity::setDatabase($database);
        return $database;
    }

    protected function setUp(): void {
        parent::setUp();
        $this->createDatabase();
    }

    public function testEagerLoadBelongsToRelationship(): void {
        // Fetch without eager loading first to establish baseline
        $entity = TestEntityWithRelationships::newQuery()->select();

        // Now test with eager loading using load()
        $collection = new EntityCollection([$entity]);
        $collection->load('related');

        // Access the relationship - should not trigger additional queries
        $related = $entity->related;

        $this->assertInstanceOf(RelatedEntity::class, $related);
        $this->assertEquals('Related 1', $related->title);
    }

    public function testEagerLoadHasOneRelationship(): void {
        // Fetch entity and eager load has_one relationship
        $entity = TestEntityWithRelationships::newQuery()->select();
        $collection = new EntityCollection([$entity]);
        $collection->load('child');

        $child = $entity->child;

        $this->assertInstanceOf(ChildEntity::class, $child);
        $this->assertEquals('Child 1', $child->title);
    }

    public function testEagerLoadHasManyRelationship(): void {
        // Fetch entity and eager load has_many relationship
        $entity = TestEntityWithRelationships::newQuery()->select();
        $collection = new EntityCollection([$entity]);
        $collection->load('children');

        $children = $entity->children;

        $this->assertInstanceOf(EntityCollection::class, $children);
        $this->assertCount(2, $children);
        $this->assertEquals('Child 1', $children[0]->title);
        $this->assertEquals('Child 2', $children[1]->title);
    }

    public function testEagerLoadWithQueryBuilderWith(): void {
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
        // Fetch all entities
        $entities = TestEntityWithRelationships::newQuery()->select();

        // Eager load relationships
        $entities->load('related');

        // Verify all relationships are loaded
        $this->assertEquals('Related 1', $entities[0]->related->title);
        $this->assertEquals('Related 2', $entities[1]->related->title);
    }

    public function testAvoidReloadingAlreadyLoadedRelationships(): void {
        $entity = TestEntityWithRelationships::newQuery()->select();

        // Load the relationship first time
        $related1 = $entity->related;

        // Try to eager load again - should not reload
        $collection = new EntityCollection([$entity]);
        $collection->load('related');

        // Should still be the same instance
        $related2 = $entity->related;

        $this->assertSame($related1, $related2);
    }
}
