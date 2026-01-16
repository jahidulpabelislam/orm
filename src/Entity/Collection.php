<?php

declare(strict_types=1);

namespace JPI\ORM\Entity;

use JPI\Database\Query\Result\CollectionInterface;
use JPI\ORM\Entity;
use JPI\Utils\Collection as BaseCollection;

class Collection extends BaseCollection implements CollectionInterface {

    /**
     * Eager load a belongs_to relationship.
     *
     * @param string $relationName
     * @param array $mapping
     * @return void
     */
    protected function eagerLoadBelongsTo(string $relationName, array $mapping): void {
        $foreignKeys = [];

        foreach ($this as $entity) {
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

        foreach ($this as $entity) {
            $foreignKey = $entity->getForeignKeyValue($relationName);
            if ($foreignKey !== null && isset($relatedEntitiesById[$foreignKey])) {
                $entity->setEagerLoadedRelationship($relationName, $relatedEntitiesById[$foreignKey]);
            }
        }
    }

    /**
     * Eager load a has_many relationship.
     *
     * @param string $relationName
     * @param array $mapping
     * @return void
     */
    protected function eagerLoadHasMany(string $relationName, array $mapping): void {
        $ids = [];

        foreach ($this as $entity) {
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

        foreach ($this as $entity) {
            $entityId = $entity->getId();
            $related = $relatedEntitiesByParentId[$entityId] ?? [];
            $entity->setEagerLoadedRelationship($relationName, $related);
        }
    }

    /**
     * Eager load a has_one relationship.
     *
     * @param string $relationName
     * @param array $mapping
     * @return void
     */
    protected function eagerLoadHasOne(string $relationName, array $mapping): void {
        $ids = [];

        foreach ($this as $entity) {
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

        foreach ($this as $entity) {
            $entityId = $entity->getId();
            $related = $relatedEntitiesByParentId[$entityId] ?? null;
            $entity->setEagerLoadedRelationship($relationName, $related);
        }
    }

    /**
     * Eager load a single relationship on the given entities.
     *
     * @param string $relation
     * @param string $entityClass The entity class to get data mapping from
     * @return void
     */
    protected function eagerLoadRelation(string $relation, string $entityClass): void {
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
            $this->eagerLoadBelongsTo($relationName, $mapping);
        } elseif ($type === 'has_many') {
            $this->eagerLoadHasMany($relationName, $mapping);
        } elseif ($type === 'has_one') {
            $this->eagerLoadHasOne($relationName, $mapping);
        }

        // Handle nested relationships
        if (!empty($nestedRelations)) {
            $nestedRelation = implode('.', $nestedRelations);
            $relatedEntities = [];

            foreach ($this as $entity) {
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
                $this->eagerLoadRelation($relatedEntities, $nestedRelation, $relatedEntityClass);
            }
        }
    }

    /**
     * Eager load relationships on the given entities.
     *
     * @param string|array<string> $relations
     * @param string $entityClass The entity class to get data mapping from
     * @return void
     */
    public function eagerLoadRelationships(string|array $relations, string $entityClass): void {
        if (is_string($relations)) {
            $relations = [$relations];
        }

        foreach ($relations as $relation) {
            $this->eagerLoadRelation( $relation, $entityClass);
        }
    }

    /**
     * Eager load relationships on this collection.
     *
     * @param string|array<string> $relations
     * @return static
     */
    public function load(string|array $relations): static {
        if (empty($this->items)) {
            return $this;
        }

        // Get entity class from first item
        $firstEntity = $this->items[array_key_first($this->items)];
        $entityClass = $firstEntity::class;

        $this->eagerLoadRelationships($relations, $entityClass);

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
