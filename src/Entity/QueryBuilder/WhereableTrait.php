<?php

declare(strict_types=1);

namespace JPI\ORM\Entity\QueryBuilder;

use JPI\ORM\Entity;
use JPI\ORM\Entity\Collection as EntityCollection;

/**
 * Convert the column to full column name if it's a column name, and allow passing entity as value.
 */
trait WhereableTrait {

    abstract public function getEntityInstance(): Entity;

    public function where(
        string $whereOrColumn,
        ?string $expression = null,
        EntityCollection|Entity|string|int|float|array|null $valueOrPlaceholder = null
    ): static {
        if ($expression !== null && $valueOrPlaceholder !== null && $this->getEntityInstance()::hasColumn($whereOrColumn)) {
            $whereOrColumn = $this->getEntityInstance()::getFullColumnName($whereOrColumn);
        }

        if ($valueOrPlaceholder instanceof Entity) {
            $valueOrPlaceholder = $valueOrPlaceholder->getId();
        }
        else if ($valueOrPlaceholder instanceof EntityCollection) {
            $valueOrPlaceholder = $valueOrPlaceholder->pluck("id")->toArray();
        }

        return parent::where($whereOrColumn, $expression, $valueOrPlaceholder);
    }
}
