<?php

declare(strict_types=1);

namespace JPI\ORM\Tests;

use JPI\Database;
use JPI\ORM\Entity\Collection as EntityCollection;
use JPI\ORM\Tests\Fixtures\ChildEntity;
use JPI\ORM\Tests\Fixtures\RelatedEntity;
use JPI\ORM\Tests\Fixtures\TestEntityWithRelationships;
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

    private Database $database;

    protected function setUp(): void {
        parent::setUp();
        
        // Create in-memory SQLite database
        $this->database = new Database("sqlite::memory:");
        
        // Set up database for test entities
        TestEntityWithRelationships::setDatabase($this->database);
        RelatedEntity::setDatabase($this->database);
        ChildEntity::setDatabase($this->database);
        
        // Create tables
        $this->database->query("
            CREATE TABLE main_table (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT,
                age INTEGER,
                price REAL,
                tags TEXT,
                birth_date TEXT,
                other_related_id INTEGER,
                created_at TEXT
            )
        ");
        
        $this->database->query("
            CREATE TABLE related_table (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title TEXT
            )
        ");
        
        $this->database->query("
            CREATE TABLE child_table (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title TEXT,
                parent_id INTEGER
            )
        ");
    }

    protected function tearDown(): void {
        parent::tearDown();
        // Database will be automatically cleaned up
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
        // Insert test data
        $this->database->query("INSERT INTO main_table (name, age) VALUES ('Test', 25)");
        
        $entities = TestEntityWithRelationships::newQuery()->select();
        $result = $entities->load('related');
        
        $this->assertSame($entities, $result);
    }

    public function testEagerLoadBelongsToRelationship(): void {
        // Insert related entity
        $this->database->query("INSERT INTO related_table (title) VALUES ('Related 1')");
        $relatedId = (int)$this->database->lastInsertId();
        
        // Insert main entity with relationship
        $this->database->query("INSERT INTO main_table (name, other_related_id) VALUES ('Main 1', {$relatedId})");
        
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
        // Insert main entity
        $this->database->query("INSERT INTO main_table (name) VALUES ('Parent 1')");
        $parentId = (int)$this->database->lastInsertId();
        
        // Insert child entity
        $this->database->query("INSERT INTO child_table (title, parent_id) VALUES ('Child 1', {$parentId})");
        
        // Fetch entity and eager load has_one relationship
        $entity = TestEntityWithRelationships::newQuery()->select();
        $collection = new EntityCollection([$entity]);
        $collection->load('child');
        
        $child = $entity->child;
        
        $this->assertInstanceOf(ChildEntity::class, $child);
        $this->assertEquals('Child 1', $child->title);
    }

    public function testEagerLoadHasManyRelationship(): void {
        // Insert main entity
        $this->database->query("INSERT INTO main_table (name) VALUES ('Parent 1')");
        $parentId = (int)$this->database->lastInsertId();
        
        // Insert multiple child entities
        $this->database->query("INSERT INTO child_table (title, parent_id) VALUES ('Child 1', {$parentId})");
        $this->database->query("INSERT INTO child_table (title, parent_id) VALUES ('Child 2', {$parentId})");
        
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
        // Insert related entity
        $this->database->query("INSERT INTO related_table (title) VALUES ('Related 1')");
        $relatedId = (int)$this->database->lastInsertId();
        
        // Insert main entity
        $this->database->query("INSERT INTO main_table (name, other_related_id) VALUES ('Main 1', {$relatedId})");
        
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
        // Insert related entity
        $this->database->query("INSERT INTO related_table (title) VALUES ('Related 1')");
        $relatedId = (int)$this->database->lastInsertId();
        
        // Insert main entity
        $this->database->query("INSERT INTO main_table (name, other_related_id) VALUES ('Parent 1', {$relatedId})");
        $parentId = (int)$this->database->lastInsertId();
        
        // Insert child entities
        $this->database->query("INSERT INTO child_table (title, parent_id) VALUES ('Child 1', {$parentId})");
        $this->database->query("INSERT INTO child_table (title, parent_id) VALUES ('Child 2', {$parentId})");
        
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
        // Insert related entities
        $this->database->query("INSERT INTO related_table (title) VALUES ('Related 1')");
        $relatedId1 = (int)$this->database->lastInsertId();
        
        $this->database->query("INSERT INTO related_table (title) VALUES ('Related 2')");
        $relatedId2 = (int)$this->database->lastInsertId();
        
        // Insert main entities
        $this->database->query("INSERT INTO main_table (name, other_related_id) VALUES ('Main 1', {$relatedId1})");
        $this->database->query("INSERT INTO main_table (name, other_related_id) VALUES ('Main 2', {$relatedId2})");
        
        // Fetch all entities
        $entities = TestEntityWithRelationships::newQuery()->select();
        
        // Eager load relationships
        $entities->load('related');
        
        // Verify all relationships are loaded
        $this->assertEquals('Related 1', $entities[0]->related->title);
        $this->assertEquals('Related 2', $entities[1]->related->title);
    }

    public function testAvoidReloadingAlreadyLoadedRelationships(): void {
        // Insert related entity
        $this->database->query("INSERT INTO related_table (title) VALUES ('Related 1')");
        $relatedId = (int)$this->database->lastInsertId();
        
        // Insert main entity
        $this->database->query("INSERT INTO main_table (name, other_related_id) VALUES ('Main 1', {$relatedId})");
        
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
