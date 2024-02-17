<?php

declare(strict_types=1);

namespace JPI\ORM\Entity;

use JPI\Database;
use JPI\Database\Query\Builder as CoreQueryBuilder;
use JPI\ORM\Entity;
use JPI\Utils\CollectionInterface;
use JPI\Utils\Collection\PaginatedInterface as PaginatedCollectionInterface;

class QueryBuilder extends CoreQueryBuilder {

    public function __construct(
        Database $database,
        protected Entity $entityInstance
    ) {
        parent::__construct($database, $this->entityInstance::getTable());
    }

    public function column(string $column, ?string $alias = null): static {
        if ($column !== "*" && $this->entityInstance::hasColumn($column)) {
            $column = $this->entityInstance::getFullColumnName($column);
        }

        return parent::column($column, $alias);
    }

    public function where(
        string $whereOrColumn,
        ?string $expression = null,
        Entity|string|int|float|array $valueOrPlaceholder = null
    ): static {
        if ($expression !== null && $valueOrPlaceholder !== null && $this->entityInstance::hasColumn($whereOrColumn)) {
            $whereOrColumn = $this->entityInstance::getFullColumnName($whereOrColumn);
        }

        if ($valueOrPlaceholder instanceof Entity) {
            $valueOrPlaceholder = $valueOrPlaceholder->getId();
        }

        return parent::where($whereOrColumn, $expression, $valueOrPlaceholder);
    }

    public function orderBy(string $column, bool $ascDirection = true): static {
        if ($this->entityInstance::hasColumn($column)) {
            $column = $this->entityInstance::getFullColumnName($column);
        }

        return parent::orderBy($column, $ascDirection);
    }

    public function createCollectionFromResult(array $rows): CollectionInterface {
        $entities = $this->entityInstance::populateEntitiesFromDB($rows);

        return new Collection($entities);
    }

    public function createPaginatedCollectionFromResult(array $rows, int $totalCount, int $limit, int $page): PaginatedCollectionInterface {
        $entities = $this->entityInstance::populateEntitiesFromDB($rows);
        return new PaginatedCollection($entities, $totalCount, $limit, $page);
    }

    public function select(): CollectionInterface|PaginatedCollectionInterface|Entity|null {
        // Make sure we at least have a consistent order
        if (!count($this->orderBy)) {
            $this->orderBy(
                $this->entityInstance::$defaultOrderByColumn,
                $this->entityInstance::$defaultOrderByASC
            );
        }

        $result = parent::select();

        if ($this->limit === 1) {
            return $result !== null ? $this->entityInstance::populateFromDB($result) : null;
        }

        return $result;
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
