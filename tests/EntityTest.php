<?php

declare(strict_types=1);

namespace JPI\ORM\Tests;

use DateTime;
use JPI\ORM\Entity\Collection as EntityCollection;
use JPI\ORM\Entity\InvalidValueException;
use JPI\ORM\Tests\Fixtures\ChildEntity;
use JPI\ORM\Tests\Fixtures\InvalidTypeEntity;
use JPI\ORM\Tests\Fixtures\ProfileEntity;
use JPI\ORM\Tests\Fixtures\RelatedEntity;
use JPI\ORM\Tests\Fixtures\TestEntity;
use JPI\ORM\Tests\Fixtures\TestEntityWithPrefix;
use JPI\ORM\Tests\Fixtures\TestEntityWithRelationships;
use JPI\Utils\Collection;
use LogicException;
use OutOfBoundsException;
use PHPUnit\Framework\TestCase;

class EntityTest extends TestCase {

    public function testBadDataMappingThrowsLogicException(): void {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage("Invalid type `invalid_type` for `name`.");

        new InvalidTypeEntity();
    }

    public function testColumnPrefixIsAppliedToColumnNames(): void {
        $entity = new TestEntityWithPrefix();

        $this->assertEquals("prefix_name", TestEntityWithPrefix::getFullColumnName("name"));
        $this->assertEquals("prefix_status", TestEntityWithPrefix::getFullColumnName("status"));
        $this->assertEquals("prefix_id", TestEntityWithPrefix::getFullColumnName("id"));
    }

    public function testEntityWithoutPrefixReturnsColumnNameUnchanged(): void {
        $entity = new TestEntity();

        $this->assertEquals("name", TestEntity::getFullColumnName("name"));
        $this->assertEquals("age", TestEntity::getFullColumnName("age"));
        $this->assertEquals("id", TestEntity::getFullColumnName("id"));
    }

    public function testSetStringValueSuccess(): void {
        $entity = new TestEntity();
        $entity->name = "Test Name";

        $this->assertEquals("Test Name", $entity->name);
    }

    public function testSetStringValueNull(): void {
        $entity = new TestEntity();
        $entity->name = null;

        $this->assertNull($entity->name);
    }

    public function testSetStringValueInvalidThrowsException(): void {
        $entity = new TestEntity();

        $this->expectException(InvalidValueException::class);
        $this->expectExceptionMessage("`name` must be a string or null.");

        $entity->name = 123;
    }

    public function testSetIntValueSuccess(): void {
        $entity = new TestEntity();
        $entity->age = 25;

        $this->assertEquals(25, $entity->age);
        $this->assertIsInt($entity->age);
    }

    public function testSetIntValueFromNumericString(): void {
        $entity = new TestEntity();
        $entity->age = "42";

        $this->assertEquals(42, $entity->age);
        $this->assertIsInt($entity->age);
    }

    public function testSetIntValueNull(): void {
        $entity = new TestEntity();
        $entity->age = null;

        $this->assertNull($entity->age);
    }

    public function testSetIntValueInvalidThrowsException(): void {
        $entity = new TestEntity();

        $this->expectException(InvalidValueException::class);
        $this->expectExceptionMessage("`age` must be an integer or null.");

        $entity->age = "not a number";
    }

    public function testSetFloatValueSuccess(): void {
        $entity = new TestEntity();
        $entity->price = 19.99;

        $this->assertEquals(19.99, $entity->price);
        $this->assertIsFloat($entity->price);
    }

    public function testSetFloatValueFromNumericString(): void {
        $entity = new TestEntity();
        $entity->price = "29.99";

        $this->assertEquals(29.99, $entity->price);
        $this->assertIsFloat($entity->price);
    }

    public function testSetFloatValueNull(): void {
        $entity = new TestEntity();
        $entity->price = null;

        $this->assertNull($entity->price);
    }

    public function testSetFloatValueInvalidThrowsException(): void {
        $entity = new TestEntity();

        $this->expectException(InvalidValueException::class);
        $this->expectExceptionMessage("`price` must be a float or null.");

        $entity->price = "not a number";
    }

    public function testSetArrayValueSuccess(): void {
        $entity = new TestEntity();
        $entity->tags = ["tag1", "tag2", "tag3"];

        $this->assertInstanceOf(Collection::class, $entity->tags);
        $this->assertEquals(["tag1", "tag2", "tag3"], $entity->tags->getItems());
    }

    public function testSetArrayValueWithCollection(): void {
        $entity = new TestEntity();
        $collection = new Collection(["a", "b", "c"]);
        $entity->tags = $collection;

        $this->assertInstanceOf(Collection::class, $entity->tags);
        $this->assertEquals(["a", "b", "c"], $entity->tags->getItems());
    }

    public function testSetArrayValueNull(): void {
        $entity = new TestEntity();
        $entity->tags = null;

        $this->assertNull($entity->tags);
    }

    public function testSetArrayValueFromDBString(): void {
        $entity = new TestEntity();
        $entity->setValues(["tags" => "tag1,tag2,tag3"], true);

        $this->assertInstanceOf(Collection::class, $entity->tags);
        $this->assertEquals(["tag1", "tag2", "tag3"], $entity->tags->getItems());
    }

    public function testSetArrayValueInvalidThrowsException(): void {
        $entity = new TestEntity();

        $this->expectException(InvalidValueException::class);
        $this->expectExceptionMessage("`tags` must be a Collection, array or null.");

        $entity->tags = "not an array";
    }

    public function testSetDateTimeValueSuccess(): void {
        $entity = new TestEntity();
        $date = new DateTime("2024-01-15 10:30:00");
        $entity->created_at = $date;

        $this->assertInstanceOf(DateTime::class, $entity->created_at);
        $this->assertEquals("2024-01-15 10:30:00", $entity->created_at->format("Y-m-d H:i:s"));
    }

    public function testSetDateTimeValueFromString(): void {
        $entity = new TestEntity();
        $entity->created_at = "2024-01-15 10:30:00";

        $this->assertInstanceOf(DateTime::class, $entity->created_at);
        $this->assertEquals("2024-01-15 10:30:00", $entity->created_at->format("Y-m-d H:i:s"));
    }

    public function testSetDateTimeValueNull(): void {
        $entity = new TestEntity();
        $entity->created_at = null;

        $this->assertNull($entity->created_at);
    }

    public function testSetDateTimeValueInvalidThrowsException(): void {
        $entity = new TestEntity();

        $this->expectException(InvalidValueException::class);

        $entity->created_at = [];
    }

    public function testSetDateValueFromString(): void {
        $entity = new TestEntity();
        $entity->birth_date = "2000-05-20";

        $this->assertInstanceOf(DateTime::class, $entity->birth_date);
        $this->assertEquals("2000-05-20", $entity->birth_date->format("Y-m-d"));
    }

    public function testSetBelongsToValueWithEntity(): void {
        $related = new RelatedEntity();
        $related->title = "Related Title";

        $entity = new TestEntityWithRelationships();
        $entity->related = $related;

        $this->assertInstanceOf(RelatedEntity::class, $entity->related);
        $this->assertEquals("Related Title", $entity->related->title);
    }

    public function testSetBelongsToValueWithInteger(): void {
        $entity = new TestEntityWithRelationships();
        $entity->related = 5;

        // When setting an integer, the database_value is stored
        // The actual entity is not loaded until accessed
        $reflection = new \ReflectionClass($entity);
        $property = $reflection->getProperty('data');
        $property->setAccessible(true);
        $data = $property->getValue($entity);

        $this->assertEquals(5, $data['related']['database_value']);
    }

    public function testSetBelongsToValueNull(): void {
        $entity = new TestEntityWithRelationships();
        $entity->related = null;

        $this->assertNull($entity->related);
    }

    public function testSetBelongsToValueInvalidThrowsException(): void {
        $entity = new TestEntityWithRelationships();

        $this->expectException(InvalidValueException::class);
        $entity->related = "invalid";
    }

    public function testSetHasManyValueWithArray(): void {
        $entity = new TestEntityWithRelationships();
        $child1 = new ChildEntity();
        $child1->title = "Child 1";
        $child2 = new ChildEntity();
        $child2->title = "Child 2";

        $entity->children = [$child1, $child2];

        $this->assertInstanceOf(EntityCollection::class, $entity->children);
        $this->assertCount(2, $entity->children);
    }

    public function testSetHasManyValueWithEntityCollection(): void {
        $entity = new TestEntityWithRelationships();
        $child = new ChildEntity();
        $child->title = "Child";

        $collection = new EntityCollection([$child]);
        $entity->children = $collection;

        $this->assertInstanceOf(EntityCollection::class, $entity->children);
        $this->assertCount(1, $entity->children);
    }

    public function testSetHasManyValueNull(): void {
        $entity = new TestEntityWithRelationships();
        $entity->children = null;

        $this->assertInstanceOf(EntityCollection::class, $entity->children);
        $this->assertCount(0, $entity->children);
    }

    public function testSetHasManyValueInvalidThrowsException(): void {
        $entity = new TestEntityWithRelationships();

        $this->expectException(InvalidValueException::class);
        $entity->children = "invalid";
    }

    public function testSetHasOneValueWithEntity(): void {
        $profile = new ProfileEntity();
        $profile->bio = "Bio text";

        $entity = new TestEntityWithRelationships();
        $entity->profile = $profile;

        $this->assertInstanceOf(ProfileEntity::class, $entity->profile);
        $this->assertEquals("Bio text", $entity->profile->bio);
    }

    public function testSetHasOneValueNull(): void {
        $entity = new TestEntityWithRelationships();
        $entity->profile = null;

        $this->assertNull($entity->profile);
    }

    public function testSetHasOneValueInvalidThrowsException(): void {
        $entity = new TestEntityWithRelationships();

        $this->expectException(InvalidValueException::class);
        $entity->profile = "invalid";
    }

    public function testSetValuesPopulatesFromArray(): void {
        $entity = new TestEntity();
        $entity->setValues([
            "name" => "Test",
            "age" => 30,
            "price" => 99.99,
        ]);

        $this->assertEquals("Test", $entity->name);
        $this->assertEquals(30, $entity->age);
        $this->assertEquals(99.99, $entity->price);
    }

    public function testSetValuesFromDBAppliesColumnPrefix(): void {
        $entity = new TestEntityWithPrefix();
        $entity->setValues([
            "prefix_name" => "Prefixed Name",
            "prefix_status" => 1,
        ], true);

        $this->assertEquals("Prefixed Name", $entity->name);
        $this->assertEquals(1, $entity->status);
    }

    public function testSetValuesFromDBHandlesBelongsToColumn(): void {
        $entity = new TestEntityWithRelationships();
        $entity->setValues([
            "name" => "Main Entity",
            "related_id" => 42,
        ], true);

        $this->assertEquals("Main Entity", $entity->name);
    }

    public function testGetValueReturnsCorrectValue(): void {
        $entity = new TestEntity();
        $entity->name = "Test Name";

        $this->assertEquals("Test Name", $entity->getValue("name"));
    }

    public function testGetValueForIdReturnsIdentifier(): void {
        $entity = new TestEntity();

        $this->assertNull($entity->getValue("id"));
    }

    public function testGetValueForInvalidKeyThrowsException(): void {
        $entity = new TestEntity();

        $this->expectException(OutOfBoundsException::class);
        $this->expectExceptionMessage("`invalid_key` isn't valid.");

        $entity->getValue("invalid_key");
    }

    public function testMagicGetReturnsCorrectValue(): void {
        $entity = new TestEntity();
        $entity->name = "Magic Test";

        $this->assertEquals("Magic Test", $entity->name);
    }

    public function testMagicSetThrowsExceptionForInvalidKey(): void {
        $entity = new TestEntity();

        $this->expectException(OutOfBoundsException::class);
        $this->expectExceptionMessage("`invalid` isn't valid.");

        $entity->invalid = "value";
    }

    public function testGetValuesToSaveConvertsArrayToString(): void {
        $entity = new TestEntity();
        $entity->name = "Test";
        $entity->tags = ["tag1", "tag2", "tag3"];

        // Use reflection to call protected method
        $reflection = new \ReflectionClass($entity);
        $method = $reflection->getMethod('getValuesToSave');
        $method->setAccessible(true);
        $values = $method->invoke($entity);

        $this->assertIsArray($values);
        $this->assertEquals("tag1,tag2,tag3", $values["tags"]);
    }

    public function testGetValuesToSaveConvertsDateTimeToString(): void {
        $entity = new TestEntity();
        $entity->created_at = new DateTime("2024-01-15 10:30:00");

        $reflection = new \ReflectionClass($entity);
        $method = $reflection->getMethod('getValuesToSave');
        $method->setAccessible(true);
        $values = $method->invoke($entity);

        $this->assertEquals("2024-01-15 10:30:00", $values["created_at"]);
    }

    public function testGetValuesToSaveConvertsDateToString(): void {
        $entity = new TestEntity();
        $entity->birth_date = new DateTime("2000-05-20");

        $reflection = new \ReflectionClass($entity);
        $method = $reflection->getMethod('getValuesToSave');
        $method->setAccessible(true);
        $values = $method->invoke($entity);

        $this->assertEquals("2000-05-20", $values["birth_date"]);
    }

    public function testGetValuesToSaveAppliesColumnPrefix(): void {
        $entity = new TestEntityWithPrefix();
        $entity->name = "Test";
        $entity->status = 1;

        $reflection = new \ReflectionClass($entity);
        $method = $reflection->getMethod('getValuesToSave');
        $method->setAccessible(true);
        $values = $method->invoke($entity);

        $this->assertArrayHasKey("prefix_name", $values);
        $this->assertArrayHasKey("prefix_status", $values);
        $this->assertEquals("Test", $values["prefix_name"]);
        $this->assertEquals(1, $values["prefix_status"]);
    }

    public function testGetValuesToSaveHandlesBelongsToRelationship(): void {
        $related = new RelatedEntity();
        $related->title = "Related";

        $entity = new TestEntityWithRelationships();
        $entity->name = "Main";
        $entity->related = $related;

        $reflection = new \ReflectionClass($entity);
        $method = $reflection->getMethod('getValuesToSave');
        $method->setAccessible(true);
        $values = $method->invoke($entity);

        $this->assertArrayHasKey("related_id", $values);
        $this->assertArrayNotHasKey("related", $values);
    }

    public function testGetValuesToSaveSkipsHasManyRelationships(): void {
        $entity = new TestEntityWithRelationships();
        $entity->name = "Main";
        $child = new ChildEntity();
        $child->title = "Child";
        $entity->children = [$child];

        $reflection = new \ReflectionClass($entity);
        $method = $reflection->getMethod('getValuesToSave');
        $method->setAccessible(true);
        $values = $method->invoke($entity);

        $this->assertArrayNotHasKey("children", $values);
        $this->assertArrayHasKey("name", $values);
    }

    public function testGetValuesToSaveSkipsHasOneRelationships(): void {
        $entity = new TestEntityWithRelationships();
        $entity->name = "Main";
        $profile = new ProfileEntity();
        $profile->bio = "Bio";
        $entity->profile = $profile;

        $reflection = new \ReflectionClass($entity);
        $method = $reflection->getMethod('getValuesToSave');
        $method->setAccessible(true);
        $values = $method->invoke($entity);

        $this->assertArrayNotHasKey("profile", $values);
        $this->assertArrayHasKey("name", $values);
    }

    public function testGetValuesToSaveHandlesNullValues(): void {
        $entity = new TestEntity();
        $entity->name = null;
        $entity->age = null;
        $entity->price = null;
        $entity->tags = null;
        $entity->created_at = null;
        $entity->birth_date = null;

        $reflection = new \ReflectionClass($entity);
        $method = $reflection->getMethod('getValuesToSave');
        $method->setAccessible(true);
        $values = $method->invoke($entity);

        $this->assertNull($values["name"]);
        $this->assertNull($values["age"]);
        $this->assertNull($values["price"]);
        $this->assertNull($values["tags"]);
        $this->assertNull($values["created_at"]);
        $this->assertNull($values["birth_date"]);
    }
}
