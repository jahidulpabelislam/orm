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
     */
    public function with(string ...$relations): static {
        $this->eagerLoad = array_merge($this->eagerLoad, $relations);

        return $this;
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

        $result = parent::select($withPagination);

        // Eager load relationships if specified
        if (!empty($this->eagerLoad) && $result !== null) {
            $collection = $result instanceof Entity ? new static::$collectionClass([$result]) : $result;
            $collection->load(...array_unique($this->eagerLoad));
        }

        return $result;
    }

    public function count(string $column = "*"): int {
        if ($column !== "*" && $this->entityInstance::hasColumn($column)) {
            $column = $this->entityInstance::getFullColumnName($column);
        }

        return parent::count($column);
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
