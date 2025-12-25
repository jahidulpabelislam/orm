<?php

declare(strict_types=1);

namespace JPI\ORM\Entity;

use JPI\Database;
use JPI\Database\Query\Builder as CoreQueryBuilder;
use JPI\Database\Query\Result\CollectionInterface;
use JPI\Database\Query\Result\PaginatedCollectionInterface;
use JPI\ORM\Entity;

class QueryBuilder extends CoreQueryBuilder {

    use Entity\QueryBuilder\WhereableTrait;

    /** @var class-string<CollectionInterface> */
    protected static string $collectionClass = Collection::class;

    /** @var class-string<PaginatedCollectionInterface> */
    protected static string $paginatedCollectionClass = PaginatedCollection::class;

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

    /**
     * Check if we're selecting a single record by ID.
     * This helps optimize queries by skipping unnecessary ORDER BY clauses.
     */
    protected function isSelectingSingleRecordById(): bool {
        // Must have limit of 1
        if ($this->limit !== 1) {
            return false;
        }

        // Check if WHERE clause contains an equality filter on the ID column
        $idColumn = $this->entityInstance::getFullColumnName("id");
        $whereString = (string)$this->where;
        
        // Check if WHERE clause filters by ID with equality operator
        // Pattern: "WHERE id = :id" or "WHERE (id = :id)" or "WHERE id = :id AND ..."
        if (preg_match("/WHERE\s+\(?\s*" . preg_quote($idColumn, '/') . "\s*=\s*/", $whereString)) {
            return true;
        }

        return false;
    }

    public function select(): CollectionInterface|PaginatedCollectionInterface|Entity|null {
        // Make sure we at least have a consistent order
        // Skip default ORDER BY if selecting a single record by ID (optimization)
        if (!count($this->orderBy) && !$this->isSelectingSingleRecordById()) {
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
