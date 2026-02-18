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

    public function testEagerLoadBelongsToRelationship(): void {
        $database = $this->createDatabase();

        $database->expects($this->once())
            ->method("selectFirst")
            ->willReturn(["id" => 100, "title" => "Related 1"])
        ;

        TestEntityWithRelationships::setDatabase($database);
        RelatedEntity::setDatabase($database);

        $entity = TestEntityWithRelationships::loadFromDatabaseRow(["id" => 1, "other_related_id" => 100]);

        $collection = new EntityCollection([$entity]);
        $collection->load("related");

        $related = $entity->related;
        $this->assertInstanceOf(RelatedEntity::class, $related);
        $this->assertEquals("Related 1", $related->title);
    }

    public function testEagerLoadHasOneRelationship(): void {
        $database = $this->createDatabase();

        $database->expects($this->once())
            ->method("selectAll")
            ->willReturn([
                ["id" => 200, "title" => "Child 2", "parent_id" => 2],
            ])
        ;

        TestEntityWithRelationships::setDatabase($database);
        ChildEntity::setDatabase($database);

        $entity = TestEntityWithRelationships::loadFromDatabaseRow(["id" => 2]);

        $collection = new EntityCollection([$entity]);
        $collection->load("child");

        $child = $entity->child;
        $this->assertInstanceOf(ChildEntity::class, $child);
        $this->assertEquals("Child 2", $child->title);
    }

    public function testEagerLoadHasManyRelationship(): void {
        $database = $this->createDatabase();

        $database->expects($this->once())
            ->method("selectAll")
            ->willReturn([
                ["id" => 3, "title" => "Child 3", "parent_id" => 3],
                ["id" => 4, "title" => "Child 4", "parent_id" => 3],
            ])
        ;

        TestEntityWithRelationships::setDatabase($database);
        ChildEntity::setDatabase($database);

        $entity = TestEntityWithRelationships::loadFromDatabaseRow(["id" => 3]);

        $collection = new EntityCollection([$entity]);
        $collection->load("children");

        $children = $entity->children;

        $this->assertInstanceOf(EntityCollection::class, $children);
        $this->assertCount(2, $children);
        $this->assertEquals("Child 3", $children[0]->title);
        $this->assertEquals("Child 4", $children[1]->title);
    }

    public function testEagerLoadWithQueryBuilderWith(): void {
        $database = $this->createDatabase();

        $database->expects($this->exactly(2))
            ->method("selectFirst")
            ->willReturnOnConsecutiveCalls(
                // First call: main entity query
                ["id" => 5, "other_related_id" => 500],
                // Second call: related entity query
                ["id" => 500, "title" => "Related 5"]
            )
        ;

        TestEntityWithRelationships::setDatabase($database);
        RelatedEntity::setDatabase($database);

        $entity = TestEntityWithRelationships::newQuery()
            ->with("related")
            ->limit(1)
            ->select();

        $related = $entity->related;
        $this->assertInstanceOf(RelatedEntity::class, $related);
        $this->assertEquals("Related 5", $related->title);
    }

    public function testEagerLoadMultipleRelationships(): void {
        $database = $this->createDatabase();

        $database->expects($this->exactly(2))
            ->method("selectFirst")
            ->willReturnOnConsecutiveCalls(
                // First call: main entity query
                ["id" => 6, "other_related_id" => 600],
                // Second call: related entity query
                ["id" => 600, "title" => "Related 6"],
            )
        ;

        // Third call: Children entity query
        $database->expects($this->once())
            ->method("selectAll")
            ->willReturn(
                [
                    ["id" => 600, "title" => "Child 6", "parent_id" => 6],
                    ["id" => 700, "title" => "Child 7", "parent_id" => 6],
                ],
            )
        ;

        TestEntityWithRelationships::setDatabase($database);
        RelatedEntity::setDatabase($database);
        ChildEntity::setDatabase($database);

        $entity = TestEntityWithRelationships::newQuery()
            ->with("related", "children")
            ->limit(1)
            ->select();

        $this->assertInstanceOf(RelatedEntity::class, $entity->related);
        $this->assertEquals("Related 6", $entity->related->title);

        $this->assertInstanceOf(EntityCollection::class, $entity->children);
        $this->assertCount(2, $entity->children);
    }

    public function testEagerLoadOnCollectionWithMultipleEntities(): void {
        $database = $this->createDatabase();

        $database->expects($this->once())
            ->method("selectAll")
            ->willReturn([
                ["id" => 700, "title" => "Related 7"],
                ["id" => 800, "title" => "Related 8"],
            ])
        ;

        TestEntityWithRelationships::setDatabase($database);
        RelatedEntity::setDatabase($database);

        $entities = new EntityCollection([
            TestEntityWithRelationships::loadFromDatabaseRow(["id" => 7, "other_related_id" => 700]),
            TestEntityWithRelationships::loadFromDatabaseRow(["id" => 8, "other_related_id" => 800]),
        ]);

        $entities->load("related");

        $this->assertEquals("Related 7", $entities[0]->related->title);
        $this->assertEquals("Related 8", $entities[1]->related->title);
    }

    public function testAvoidReloadingAlreadyLoadedRelationships(): void {
        $database = $this->createDatabase();

        // Should only be called once for the initial load, not again on second load()
        $database->expects($this->once())
            ->method("selectAll")
            ->willReturn([["id" => 900, "title" => "Related 1"]])
        ;

        TestEntityWithRelationships::setDatabase($database);
        RelatedEntity::setDatabase($database);

        $entity = TestEntityWithRelationships::loadFromDatabaseRow(["id" => 9, "other_related_id" => 900]);

        // Load the relationship first time
        $collection = new EntityCollection([$entity]);
        $collection->load("children");
        $children1 = $entity->children;

        // Try to eager load again - should not trigger another database query
        $collection->load("children");
        $children2 = $entity->children;

        // Should still be the same instance
        $this->assertSame($children1, $children2);
    }
}
