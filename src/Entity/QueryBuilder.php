<?php

declare(strict_types=1);

namespace JPI\ORM\Entity;

use JPI\Database;
use JPI\Database\Query\Builder as CoreQueryBuilder;
use JPI\Database\Query\Result\CollectionInterface;
use JPI\Database\Query\Result\PaginatedCollectionInterface;
use JPI\ORM\Entity;
use JPI\ORM\Entity\QueryBuilder\Clause\Where\AndCondition;
use JPI\ORM\Entity\QueryBuilder\Clause\Where\OrCondition;

class QueryBuilder extends CoreQueryBuilder {

    use Entity\QueryBuilder\WhereableTrait;

    /** @var class-string<CollectionInterface> */
    protected static string $collectionClass = Collection::class;

    /** @var class-string<PaginatedCollectionInterface> */
    protected static string $paginatedCollectionClass = PaginatedCollection::class;

    /** @var string[] Relationships to eager load */
    protected array $eagerLoad = [];

    public function __construct(
        Database $database,
        protected Entity $entityInstance
    ) {
        parent::__construct($database, $this->entityInstance::getTable());
    }

    public function getEntityInstance(): Entity {
        return $this->entityInstance;
    }

    public function column(string $column, ?string $alias = null): static {
        if ($column !== "*" && $this->entityInstance::hasColumn($column)) {
            $column = $this->entityInstance::getFullColumnName($column);
        }

        return parent::column($column, $alias);
    }

    public function orderBy(string $column, bool $ascDirection = true): static {
        if ($this->entityInstance::hasColumn($column)) {
            $column = $this->entityInstance::getFullColumnName($column);
        }

        return parent::orderBy($column, $ascDirection);
    }

    public function newAndCondition(): AndCondition {
        return new AndCondition($this);
    }

    public function newOrCondition(): OrCondition {
        return new OrCondition($this);
    }

    public function getResultClass(): string {
        return $this->entityInstance::class;
    }

    /**
     * Set the relationships that should be eager loaded.
     *
     * @param string|array<string> $relations
     * @return static
     */
    public function with(string|array $relations): static {
        if (is_string($relations)) {
            $relations = [$relations];
        }

        $this->eagerLoad = array_merge($this->eagerLoad, $relations);

        return $this;
    }

    /**
     * Eager load relationships on the given results.
     *
     * @param CollectionInterface|PaginatedCollectionInterface|Entity $results
     * @return void
     */
    protected function eagerLoadRelationships(CollectionInterface|PaginatedCollectionInterface|Entity &$results): void {
        // Convert single entity to array for uniform processing
        $entities = $results instanceof Entity ? [$results] : iterator_to_array($results);

        if (empty($entities)) {
            return;
        }

        foreach ($this->eagerLoad as $relation) {
            $this->eagerLoadRelation($entities, $relation);
        }
    }

    /**
     * Eager load a single relationship on the given entities.
     *
     * @param array<Entity> $entities
     * @param string $relation
     * @return void
     */
    protected function eagerLoadRelation(array $entities, string $relation): void {
        // Handle nested relationships (e.g., 'customer.address')
        $nestedRelations = explode('.', $relation);
        $relationName = array_shift($nestedRelations);

        $dataMapping = $this->entityInstance::getDataMapping();

        if (!isset($dataMapping[$relationName])) {
            return;
        }

        $mapping = $dataMapping[$relationName];
        $type = $mapping['type'];

        if ($type === 'belongs_to') {
            $this->eagerLoadBelongsTo($entities, $relationName, $mapping);
        } elseif ($type === 'has_many') {
            $this->eagerLoadHasMany($entities, $relationName, $mapping);
        } elseif ($type === 'has_one') {
            $this->eagerLoadHasOne($entities, $relationName, $mapping);
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
                $relatedInstance = new $relatedEntityClass();
                $relatedQuery = new self($this->database, $relatedInstance);
                $relatedQuery->with($nestedRelation);
                $relatedQuery->eagerLoadRelation($relatedEntities, $nestedRelation);
            }
        }
    }

    /**
     * Eager load a belongs_to relationship.
     *
     * @param array<Entity> $entities
     * @param string $relationName
     * @param array $mapping
     * @return void
     */
    protected function eagerLoadBelongsTo(array $entities, string $relationName, array $mapping): void {
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
    protected function eagerLoadHasMany(array $entities, string $relationName, array $mapping): void {
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

        $relatedEntities = $relatedEntityClass::newQuery()
            ->where($foreignKey, 'IN', $ids)
            ->select();

        $relatedEntitiesByParentId = [];
        foreach ($relatedEntities as $relatedEntity) {
            $parentId = $relatedEntity->getValue($foreignKey);
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
    protected function eagerLoadHasOne(array $entities, string $relationName, array $mapping): void {
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

        $relatedEntities = $relatedEntityClass::newQuery()
            ->where($mapping['column'], 'IN', $ids)
            ->select();

        $relatedEntitiesByParentId = [];
        foreach ($relatedEntities as $relatedEntity) {
            $parentId = $relatedEntity->getValue($mapping['column']);
            $relatedEntitiesByParentId[$parentId] = $relatedEntity;
        }

        foreach ($entities as $entity) {
            $entityId = $entity->getId();
            $related = $relatedEntitiesByParentId[$entityId] ?? null;
            $entity->setEagerLoadedRelationship($relationName, $related);
        }
    }

    public function select(bool $withPagination = true): CollectionInterface|PaginatedCollectionInterface|Entity|null {
        // Force limit of 1 when selecting a single record by ID
        $idColumn = $this->entityInstance::getFullColumnName("id");
        if (count($this->where) === 1 && (string)$this->where[0] === "$idColumn = :$idColumn") {
            $this->limit(1);
        }
        else if (!count($this->orderBy)) {
            // Make sure we at least have a consistent order
            $this->orderBy(
                $this->entityInstance::$defaultOrderByColumn,
                $this->entityInstance::$defaultOrderByASC
            );
        }

        $results = parent::select($withPagination);

        // Eager load relationships if specified
        if (!empty($this->eagerLoad) && $results !== null) {
            $this->eagerLoadRelationships($results);
        }

        return $results;
    }

    public function insert(array $values): ?int {
        $updatedValues = [];
        foreach ($values as $column => $value) {
            if ($this->entityInstance::hasColumn($column)) {
                $column = $this->entityInstance::getFullColumnName($column);
            }

            $updatedValues[$column] = $value;
        }

        return parent::insert($updatedValues);
    }

    public function update(array $values): int {
        $updatedValues = [];
        foreach ($values as $column => $value) {
            if ($this->entityInstance::hasColumn($column)) {
                $column = $this->entityInstance::getFullColumnName($column);
            }

            $updatedValues[$column] = $value;
        }

        return parent::update($updatedValues);
    }
}
