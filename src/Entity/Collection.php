<?php

declare(strict_types=1);

namespace JPI\ORM\Entity;

use JPI\Database\Query\Result\CollectionInterface;
use JPI\ORM\Entity;
use JPI\Utils\Collection as BaseCollection;

class Collection extends BaseCollection implements CollectionInterface {

    protected static function eagerLoadBelongsTo(array $entities, string $relationName, array $mapping): void {
        $foreignKeys = [];
        foreach ($entities as $entity) {
            $foreignKey = $entity->getForeignKeyValue($relationName);
            if ($foreignKey !== null && !isset($entity->$relationName)) {
                $foreignKeys[$foreignKey] = true;
            }
        }

        if (empty($foreignKeys)) {
            return;
        }

        $relatedEntities = $mapping["entity"]::newQuery()->where("id", "IN", array_keys($foreignKeys))->select();
        $relatedEntities = $relatedEntities instanceof Entity ? [$relatedEntities] : $relatedEntities;

        $relatedEntitiesById = [];
        foreach ($relatedEntities as $relatedEntity) {
            $relatedEntitiesById[$relatedEntity->getId()] = $relatedEntity;
        }

        foreach ($entities as $entity) {
            $foreignKey = $entity->getForeignKeyValue($relationName);
            if ($foreignKey !== null && isset($relatedEntitiesById[$foreignKey])) {
                $entity->setValue($relationName, $relatedEntitiesById[$foreignKey], true);
            }
        }
    }

    protected static function eagerLoadHasOneOrMany(array $entities, string $relationName, array $mapping, bool $isMany): void {
        $ids = [];
        foreach ($entities as $entity) {
            if ($entity->getId() !== null && !isset($entity->$relationName)) {
                $ids[$entity->getId()] = true;
            }
        }

        if (empty($ids)) {
            return;
        }

        $relatedEntityClass = $mapping["entity"];
        $relatedDataMapping = $relatedEntityClass::getDataMapping();
        $foreignKeyRelationName = $mapping["column"];

        if (!isset($relatedDataMapping[$foreignKeyRelationName])) {
            return;
        }

        $foreignKey = $relatedDataMapping[$foreignKeyRelationName]["column"];

        $relatedEntities = $relatedEntityClass::newQuery()->where($foreignKey, "IN", array_keys($ids))->select();

        $relatedEntitiesByParentId = [];
        foreach ($relatedEntities as $relatedEntity) {
            $parentId = $relatedEntity->getForeignKeyValue($foreignKeyRelationName);
            if (!isset($relatedEntitiesByParentId[$parentId])) {
                $relatedEntitiesByParentId[$parentId] = [];
            }
            $relatedEntitiesByParentId[$parentId][] = $relatedEntity;
        }

        foreach ($entities as $entity) {
            $value = $isMany ? ($relatedEntitiesByParentId[$entity->getId()] ?? []) : ($relatedEntitiesByParentId[$entity->getId()][0] ?? null);
            $entity->setValue($relationName, $value ?? ($isMany ? [] : null), true);
        }
    }

    protected static function eagerLoadRelation(array $entities, string $relation): void {
        if (empty($entities)) {
            return;
        }

        // Get entity class from first item
        $firstEntity = $entities[array_key_first($entities)];

        // Skip if first item is not an Entity
        if (!($firstEntity instanceof Entity)) {
            return;
        }

        $entityClass = get_class($firstEntity);

        // Handle nested relationships (e.g., "customer.address")
        $nestedRelations = explode(".", $relation);
        $relationName = array_shift($nestedRelations);

        $dataMapping = call_user_func([$entityClass, "getDataMapping"]);

        if (!isset($dataMapping[$relationName])) {
            return;
        }

        $mapping = $dataMapping[$relationName];
        $type = $mapping["type"];

        if ($type === "belongs_to") {
            static::eagerLoadBelongsTo($entities, $relationName, $mapping);
        }
        else if ($type === "has_many") {
            static::eagerLoadHasOneOrMany($entities, $relationName, $mapping, true);
        }
        else if ($type === "has_one") {
            static::eagerLoadHasOneOrMany($entities, $relationName, $mapping, false);
        }

        // Handle nested relationships
        if (!empty($nestedRelations)) {
            $nestedRelation = implode(".", $nestedRelations);
            $relatedEntities = [];

            foreach ($entities as $entity) {
                $related = $entity->$relationName;
                if ($related instanceof Entity) {
                    $relatedEntities[] = $related;
                }
                else if ($related instanceof Collection) {
                    foreach ($related as $item) {
                        $relatedEntities[] = $item;
                    }
                }
            }

            if (!empty($relatedEntities)) {
                static::eagerLoadRelation($relatedEntities, $nestedRelation);
            }
        }
    }

    /**
     * Eager load relationships on this collection.
     */
    public function load(string ...$relations): static {
        if (!empty($this->items)) {
            foreach ($relations as $relation) {
                static::eagerLoadRelation($this->items, $relation);
            }
        }

        return $this;
    }

    public function toArray(?Entity $parentEntity = null): array {
        $array = [];

        foreach ($this->items as $key => $item) {
            $array[$key] = $item->toArray($parentEntity);
        }

        return $array;
    }
}
