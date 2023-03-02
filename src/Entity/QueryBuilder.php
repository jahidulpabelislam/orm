<?php

declare(strict_types=1);

namespace JPI\ORM\Entity;

use JPI\Database;
use JPI\Database\Query\Builder as CoreQueryBuilder;
use JPI\ORM\Entity;

class QueryBuilder extends CoreQueryBuilder {

    protected $entityInstance;

    public function __construct(Database $database, Entity $entity) {
        $this->entityInstance = $entity;

        parent::__construct($database, $entity::getTable());
    }

    public function createCollectionFromResult(array $rows): Collection {
        $entities = $this->entityInstance::populateEntitiesFromDB($rows);

        return new Collection($entities);
    }

    public function createPaginatedCollectionFromResult(array $rows, int $totalCount, int $limit, int $page): PaginatedCollection {
        $entities = $this->entityInstance::populateEntitiesFromDB($rows);
        return new PaginatedCollection($entities, $totalCount, $limit, $page);
    }

    public function select() {
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
}
