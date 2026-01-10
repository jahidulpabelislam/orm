<?php

declare(strict_types=1);

namespace JPI\ORM\Entity;

use JPI\ORM\Entity;

/**
 * Trait for eager loading relationships on entities.
 */
trait EagerLoadable {

    /**
     * Eager load a belongs_to relationship.
     *
     * @param array<Entity> $entities
     * @param string $relationName
     * @param array $mapping
     * @return void
     */
    protected static function eagerLoadBelongsTo(array $entities, string $relationName, array $mapping): void {
        $foreignKeys = [];

        foreach ($entities as $entity) {
            $foreignKey = $entity->getForeignKeyValue($relationName);
            if ($foreignKey !== null) {
                $foreignKeys[] = $foreignKey;
            }
        }

        if (empty($foreignKeys)) {
            return;
        }

        $foreignKeys = array_unique($foreignKeys);
        $relatedEntityClass = $mapping['entity'];

        $relatedEntities = $relatedEntityClass::newQuery()
            ->where('id', 'IN', $foreignKeys)
            ->select();

        $relatedEntities = $relatedEntities instanceof Entity ? [$relatedEntities] : $relatedEntities;

        $relatedEntitiesById = [];
        foreach ($relatedEntities as $relatedEntity) {
            $relatedEntitiesById[$relatedEntity->getId()] = $relatedEntity;
        }

        foreach ($entities as $entity) {
            $foreignKey = $entity->getForeignKeyValue($relationName);
            if ($foreignKey !== null && isset($relatedEntitiesById[$foreignKey])) {
                $entity->setEagerLoadedRelationship($relationName, $relatedEntitiesById[$foreignKey]);
            }
        }
    }

    /**
     * Eager load a has_many relationship.
     *
     * @param array<Entity> $entities
     * @param string $relationName
     * @param array $mapping
     * @return void
     */
    protected static function eagerLoadHasMany(array $entities, string $relationName, array $mapping): void {
        $ids = [];

        foreach ($entities as $entity) {
            if ($entity->getId() !== null) {
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

        $relatedEntities = $relatedEntityClass::newQuery()
            ->where($foreignKey, 'IN', $ids)
            ->select();

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
            $entity->setEagerLoadedRelationship($relationName, $related);
        }
    }

    /**
     * Eager load a has_one relationship.
     *
     * @param array<Entity> $entities
     * @param string $relationName
     * @param array $mapping
     * @return void
     */
    protected static function eagerLoadHasOne(array $entities, string $relationName, array $mapping): void {
        $ids = [];

        foreach ($entities as $entity) {
            if ($entity->getId() !== null) {
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

        $relatedEntities = $relatedEntityClass::newQuery()
            ->where($foreignKey, 'IN', $ids)
            ->select();

        $relatedEntitiesByParentId = [];
        foreach ($relatedEntities as $relatedEntity) {
            $parentId = $relatedEntity->getForeignKeyValue($foreignKeyRelationName);
            $relatedEntitiesByParentId[$parentId] = $relatedEntity;
        }

        foreach ($entities as $entity) {
            $entityId = $entity->getId();
            $related = $relatedEntitiesByParentId[$entityId] ?? null;
            $entity->setEagerLoadedRelationship($relationName, $related);
        }
    }

    /**
     * Eager load a single relationship on the given entities.
     *
     * @param array<Entity> $entities
     * @param string $relation
     * @param string $entityClass The entity class to get data mapping from
     * @return void
     */
    protected static function eagerLoadRelation(array $entities, string $relation, string $entityClass): void {
        if (empty($entities)) {
            return;
        }

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
        } elseif ($type === 'has_many') {
            static::eagerLoadHasMany($entities, $relationName, $mapping);
        } elseif ($type === 'has_one') {
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
                } elseif ($related instanceof Collection) {
                    foreach ($related as $item) {
                        $relatedEntities[] = $item;
                    }
                }
            }

            if (!empty($relatedEntities)) {
                $relatedEntityClass = $mapping['entity'];
                static::eagerLoadRelation($relatedEntities, $nestedRelation, $relatedEntityClass);
            }
        }
    }

    /**
     * Eager load relationships on the given entities.
     *
     * @param array<Entity> $entities
     * @param string|array<string> $relations
     * @param string $entityClass The entity class to get data mapping from
     * @return void
     */
    protected static function eagerLoadRelationships(array $entities, string|array $relations, string $entityClass): void {
        if (empty($entities)) {
            return;
        }

        if (is_string($relations)) {
            $relations = [$relations];
        }

        foreach ($relations as $relation) {
            static::eagerLoadRelation($entities, $relation, $entityClass);
        }
    }
}
