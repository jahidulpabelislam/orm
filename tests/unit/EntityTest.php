<?php

declare(strict_types=1);

namespace JPI\ORM\Tests\Unit;

use DateTime;
use JPI\ORM\Entity\Collection as EntityCollection;
use JPI\ORM\Entity\InvalidValueException;
use JPI\ORM\Tests\Fixtures\ChildEntity;
use JPI\ORM\Tests\Fixtures\InvalidTypeEntity;
use JPI\ORM\Tests\Fixtures\RelatedEntity;
use JPI\ORM\Tests\Fixtures\TestEntity;
use JPI\ORM\Tests\Fixtures\TestEntityWithPrefix;
use JPI\ORM\Tests\Fixtures\TestEntityWithRelationships;
use JPI\Utils\Collection;
use LogicException;
use OutOfBoundsException;
use PHPUnit\Framework\TestCase;

/**
 * @covers \JPI\ORM\Entity
 */
final class EntityTest extends TestCase {

    public function testInvalidDataMapping(): void {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage("Invalid type `invalid_type` for `name`.");
        new InvalidTypeEntity();
    }

    public function testGetColumns(): void {
        $this->assertSame(["name", "age", "price", "tags", "birth_date", "created_at"], TestEntity::getColumns());

        // With prefix
        $this->assertSame(["name", "age", "price", "tags", "birth_date", "created_at"], TestEntityWithPrefix::getColumns());

        // With relationships
        $this->assertSame(["name", "age", "price", "tags", "birth_date", "other_related_id", "created_at"], TestEntityWithRelationships::getColumns());

        // With belongs_to but no explicit column
        $this->assertSame(["title", "parent_id"], ChildEntity::getColumns());
    }

    public function testGetFullColumnName(): void {
        $this->assertSame("name", TestEntity::getFullColumnName("name"));
        $this->assertSame("id", TestEntity::getFullColumnName("id"));

        // With prefix
        $this->assertSame("prefix_name", TestEntityWithPrefix::getFullColumnName("name"));
        $this->assertSame("prefix_id", TestEntityWithPrefix::getFullColumnName("id"));
    }

    public function testSetStringValue(): void {
        $entity = new TestEntity();

        $entity->name = "Test Name";
        $this->assertSame("Test Name", $entity->name);

        $entity->name = null;
        $this->assertNull($entity->name);

        $this->expectException(InvalidValueException::class);
        $this->expectExceptionMessage("`name` must be a string or null.");
        $entity->name = 123;
    }

    public function testSetIntValue(): void {
        $entity = new TestEntity();

        $entity->age = 25;
        $this->assertSame(25, $entity->age);

        $entity->age = "42";
        $this->assertSame(42, $entity->age);

        $entity->age = null;
        $this->assertNull($entity->age);

        $this->expectException(InvalidValueException::class);
        $this->expectExceptionMessage("`age` must be an integer or null.");
        $entity->age = "not a number";
    }

    public function testSetFloatValidValue(): void {
        $entity = new TestEntity();

        $entity->price = 19.99;
        $this->assertSame(19.99, $entity->price);

        $entity->price = "29.99";
        $this->assertSame(29.99, $entity->price);

        $entity->price = null;
        $this->assertNull($entity->price);

        $this->expectException(InvalidValueException::class);
        $this->expectExceptionMessage("`price` must be a float or null.");
        $entity->price = "not a number";
    }

    public function testSetArrayValue(): void {
        $entity = new TestEntity();

        $entity->tags = ["tag1", "tag2", "tag3"];
        $this->assertSame(Collection::class, $entity->tags::class);
        $this->assertSame(["tag1", "tag2", "tag3"], $entity->tags->getItems());
        $this->assertSame("tag2", $entity->tags[1]);

        $entity->tags = new Collection(["a", "b", "c"]);
        $this->assertSame(Collection::class, $entity->tags::class);
        $this->assertSame(["a", "b", "c"], $entity->tags->getItems());
        $this->assertSame("c", $entity->tags[2]);

        $entity->tags = null;
        $this->assertNull($entity->tags);

        $entity->setValues(["tags" => "tag1,tag2,tag3"], true);
        $this->assertSame(Collection::class, $entity->tags::class);
        $this->assertSame(["tag1", "tag2", "tag3"], $entity->tags->getItems());

        $this->expectException(InvalidValueException::class);
        $this->expectExceptionMessage("`tags` must be a Collection, array or null.");
        $entity->tags = "not an array";
    }

    public function testSetDateTimeValue(): void {
        $entity = new TestEntity();

        $entity->created_at = new DateTime("2024-01-15 18:45:00");
        $this->assertSame(DateTime::class, $entity->created_at::class);
        $this->assertSame("January 15, 2024, 6:45 pm", $entity->created_at->format("F j, Y, g:i a"));

        $entity->created_at = "2024-06-21 10:30:00";
        $this->assertSame(DateTime::class, $entity->created_at::class);
        $this->assertSame("June 21, 2024, 10:30 am", $entity->created_at->format("F j, Y, g:i a"));

        $entity->created_at = null;
        $this->assertNull($entity->created_at);

        $this->expectException(InvalidValueException::class);
        $entity->created_at = "invalid date time";
    }

    public function testSetDateValue(): void {
        $entity = new TestEntity();
        $entity->birth_date = "2000-05-20";

        $this->assertSame(DateTime::class, $entity->birth_date::class);
        $this->assertSame("May 20, 2000", $entity->birth_date->format("F j, Y"));
    }

    public function testSetBelongsToValue(): void {
        $entity = new TestEntityWithRelationships();

        // With id
        $entity->related = 5;
        $this->assertSame(5, $entity->other_related_id);

        // With Entity instance
        $related = RelatedEntity::loadFromDatabaseRow(["id" => 11]);
        $related->title = "Related Title";
        $entity->related = $related;
        $this->assertSame(RelatedEntity::class, $entity->related::class);
        $this->assertSame("Related Title", $entity->related->title);
        $this->assertSame(11, $entity->other_related_id);

        $entity->related = null;
        $this->assertNull($entity->related);

        $this->expectException(InvalidValueException::class);
        $entity->related = "invalid";
    }

    public function testSetHasManyValue(): void {
        $entity = new TestEntityWithRelationships();

        $child1 = new ChildEntity();
        $child1->title = "Child 1";
        $child2 = new ChildEntity();
        $child2->title = "Child 2";

        $entity->children = [$child1, $child2];
        $this->assertSame(EntityCollection::class, $entity->children::class);
        $this->assertCount(2, $entity->children);
        $this->assertSame("Child 2", $entity->children[1]->title);

        $entity->children = new EntityCollection([$child2]);
        $this->assertSame(EntityCollection::class, $entity->children::class);
        $this->assertCount(1, $entity->children);
        $this->assertSame("Child 2", $entity->children[0]->title);

        $entity->children = null;
        $this->assertSame(EntityCollection::class, $entity->children::class);
        $this->assertCount(0, $entity->children);

        $this->expectException(InvalidValueException::class);
        $entity->children = "invalid";
    }

    public function testSetHasOneValidValue(): void {
        $entity = new TestEntityWithRelationships();

        $child = new ChildEntity();
        $child->title = "Title text";

        $entity->child = $child;
        $this->assertSame(ChildEntity::class, $entity->child::class);
        $this->assertSame("Title text", $entity->child->title);

        $entity->child = null;
        $this->assertNull($entity->child);

        $this->expectException(InvalidValueException::class);
        $entity->child = "invalid";
    }

    public function testSetValues(): void {
        $entity = new TestEntityWithRelationships();
        $entity->setValues([
            "name" => "Test",
            "age" => 30,
            "price" => 99.99,
            "related" => 42, // Belongs to relationship (using key name)
        ]);
        $this->assertSame("Test", $entity->name);
        $this->assertSame(30, $entity->age);
        $this->assertSame(99.99, $entity->price);
        $this->assertSame(42, $entity->other_related_id);

        // Belongs to with id from db (Need to use the real column name)
        $entity = new TestEntityWithRelationships();
        $entity->setValues([
            "other_related_id" => 44,
        ], true);
        $this->assertSame(44, $entity->other_related_id);

        // From db with prefix
        $entity = new TestEntityWithPrefix();
        $entity->setValues([
            "prefix_name" => "Prefixed Name",
            "prefix_age" => 66,
        ], true);
        $this->assertSame("Prefixed Name", $entity->name);
        $this->assertSame(66, $entity->age);
    }

    public function testGetId(): void {
        $entity = new TestEntity();
        $this->assertNull($entity->id);

        $entity = TestEntity::loadFromDatabaseRow(["id" => 69]);
        $this->assertSame(69, $entity->id);
    }

    public function testMagicGetForInvalidKey(): void {
        $this->expectException(OutOfBoundsException::class);
        $this->expectExceptionMessage("`invalid_key` isn't valid.");

        $entity = new TestEntity();
        $entity->invalid_key;
    }

    public function testMagicSetForInvalidKey(): void {
        $this->expectException(OutOfBoundsException::class);
        $this->expectExceptionMessage("`invalid` isn't valid.");

        $entity = new TestEntity();
        $entity->invalid = "value";
    }

    public function testGetValuesToSave(): void {
        // Should only include the foreign key for belongs_to relationships and ignore has_one/has_many
        $entity = new TestEntityWithRelationships();
        $reflection = new \ReflectionClass($entity);
        $method = $reflection->getMethod('getValuesToSave');

        // Initially all null
        $this->assertSame(
            [
                "name" => null,
                "age" => null,
                "price" => null,
                "tags" => null,
                "birth_date" => null,
                "other_related_id" => null,
                "created_at" => null,
            ],
            $method->invoke($entity)
        );

        $child = ChildEntity::loadFromDatabaseRow(["id" => 10]);
        $child->title = "Child";

        $entity->name = "Main";
        $entity->age = 25;
        $entity->price = 99.99;
        $entity->tags = new Collection(["tag1", "tag2", "tag3"]);
        $entity->birth_date = new DateTime("2000-05-20");
        $entity->related = RelatedEntity::loadFromDatabaseRow(["id" => 42]);
        $entity->child = $child;
        $entity->children = [$child];
        $entity->created_at = new DateTime("2024-01-15 10:30:00");

        $this->assertSame(
            [
                "name" => "Main",
                "age" => 25,
                "price" => 99.99,
                "tags" => "tag1,tag2,tag3",
                "birth_date" => "2000-05-20",
                "other_related_id" => 42,
                "created_at" => "2024-01-15 10:30:00",
            ],
            $method->invoke($entity)
        );
    }

    public function testGetValuesToSaveWithPrefix(): void {
        $entity = new TestEntityWithPrefix();
        $reflection = new \ReflectionClass($entity);
        $method = $reflection->getMethod('getValuesToSave');

        $entity->name = "Test";
        $entity->age = 1;
        $this->assertSame(
            [
                "prefix_name" => "Test",
                "prefix_age" => 1,
                "prefix_price" => null,
                "prefix_tags" => null,
                "prefix_birth_date" => null,
                "prefix_created_at" => null,
            ],
            $method->invoke($entity)
        );
    }
}
