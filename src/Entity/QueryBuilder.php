<?php

declare(strict_types=1);

namespace JPI\ORM\Entity;

use JPI\Database;
use JPI\Database\Query\Builder as CoreQueryBuilder;
use JPI\ORM\Entity;
use JPI\Utils\CollectionInterface;
use JPI\Utils\Collection\PaginatedInterface as PaginatedCollectionInterface;

class QueryBuilder extends CoreQueryBuilder {

    use Entity\QueryBuilder\WhereableTrait;

    /** @var class-string<CollectionInterface> */
    protected static $collectionClass = Collection::class;

    /** @var class-string<PaginatedCollectionInterface> */
    protected static $paginatedCollectionClass = PaginatedCollection::class;

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

    public function getResultClass(): string {
        return $this->entityInstance::class;
    }

    public function select(): CollectionInterface|PaginatedCollectionInterface|Entity|null {
        // Make sure we at least have a consistent order
        if (!count($this->orderBy)) {
            $this->orderBy(
                $this->entityInstance::$defaultOrderByColumn,
                $this->entityInstance::$defaultOrderByASC
            );
        }

        return parent::select();
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
