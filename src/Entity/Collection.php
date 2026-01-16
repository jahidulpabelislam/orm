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
            if (!isset($entity->$relationName) && $foreignKey !== null) {
                $foreignKeys[] = $foreignKey;
            }
        }

        if (empty($foreignKeys)) {
            return;
        }

        $foreignKeys = array_unique($foreignKeys);
        $relatedEntityClass = $mapping['entity'];

        $relatedEntities = $relatedEntityClass::newQuery()->where('id', 'IN', $foreignKeys)->select();

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

    protected static function eagerLoadHasMany(array $entities, string $relationName, array $mapping): void {
        $ids = [];

        foreach ($entities as $entity) {
            if (!isset($entity->$relationName) && $entity->getId() !== null) {
                $ids[] = $entity->getId();
            }
        }

        if (empty($ids)) {
            return;
        }

        $relatedEntityClass = $mapping['entity'];
        $relatedDataMapping = $relatedEntityClass::getDataMapping();

        if (!isset($relatedDataMapping[$mapping['column']])) {
            return;
        }

        $relatedEntityMap = $relatedDataMapping[$mapping['column']];
        $foreignKey = $relatedEntityMap['column'];
        $foreignKeyRelationName = $mapping['column'];

        $relatedEntities = $relatedEntityClass::newQuery()->where($foreignKey, 'IN', $ids)->select();

        $relatedEntitiesByParentId = [];
        foreach ($relatedEntities as $relatedEntity) {
            $parentId = $relatedEntity->getForeignKeyValue($foreignKeyRelationName);
            if (!isset($relatedEntitiesByParentId[$parentId])) {
                $relatedEntitiesByParentId[$parentId] = [];
            }
            $relatedEntitiesByParentId[$parentId][] = $relatedEntity;
        }

        foreach ($entities as $entity) {
            $entityId = $entity->getId();
            $related = $relatedEntitiesByParentId[$entityId] ?? [];
            $entity->setValue($relationName, $related, true);
        }
    }

    protected static function eagerLoadHasOne(array $entities, string $relationName, array $mapping): void {
        $ids = [];

        foreach ($entities as $entity) {
            if (!isset($entity->$relationName) && $entity->getId() !== null) {
                $ids[] = $entity->getId();
            }
        }

        if (empty($ids)) {
            return;
        }

        $relatedEntityClass = $mapping['entity'];
        $relatedDataMapping = $relatedEntityClass::getDataMapping();
        $foreignKeyRelationName = $mapping['column'];

        if (!isset($relatedDataMapping[$foreignKeyRelationName])) {
            return;
        }

        $relatedEntityMap = $relatedDataMapping[$foreignKeyRelationName];
        $foreignKey = $relatedEntityMap['column'];

        $relatedEntities = $relatedEntityClass::newQuery()->where($foreignKey, 'IN', $ids)->select();

        $relatedEntitiesByParentId = [];
        foreach ($relatedEntities as $relatedEntity) {
            $parentId = $relatedEntity->getForeignKeyValue($foreignKeyRelationName);
            $relatedEntitiesByParentId[$parentId] = $relatedEntity;
        }

        foreach ($entities as $entity) {
            $entityId = $entity->getId();
            $related = $relatedEntitiesByParentId[$entityId] ?? null;
            $entity->setValue($relationName, $related, true);
        }
    }

    /**
     * Eager load a single relationship on the given entities.
     */
    protected static function eagerLoadRelation(array $entities, string $relation): void {
        // Get entity class from first item
        $firstEntity = $entities[array_key_first($entities)];
        $entityClass = $firstEntity::class;

        // Handle nested relationships (e.g., 'customer.address')
        $nestedRelations = explode('.', $relation);
        $relationName = array_shift($nestedRelations);

        $dataMapping = $entityClass::getDataMapping();

        if (!isset($dataMapping[$relationName])) {
            return;
        }

        $mapping = $dataMapping[$relationName];
        $type = $mapping['type'];

        if ($type === 'belongs_to') {
            static::eagerLoadBelongsTo($entities, $relationName, $mapping);
        }
        else if ($type === 'has_many') {
            static::eagerLoadHasMany($entities, $relationName, $mapping);
        }
        else if ($type === 'has_one') {
            static::eagerLoadHasOne($entities, $relationName, $mapping);
        }

        // Handle nested relationships
        if (!empty($nestedRelations)) {
            $nestedRelation = implode('.', $nestedRelations);
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
    public function load(array|string $relations): static {
        if (empty($this->items)) {
            return $this;
        }

        if (is_string($relations)) {
            $relations = [$relations];
        }

        foreach ($relations as $relation) {
            static::eagerLoadRelation($this->items, $relation);
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
