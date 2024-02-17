<?php

declare(strict_types=1);

namespace JPI\ORM\Entity\QueryBuilder;

use JPI\ORM\Entity;

/**
 * Convert the column to full column name if it's a column name, and allow passing entity as value.
 */
trait WhereableTrait {

    abstract public function getEntityInstance(): Entity;

    public function where(
        string $whereOrColumn,
        ?string $expression = null,
        Entity|string|int|float|array $valueOrPlaceholder = null
    ): static {
        if ($expression !== null && $valueOrPlaceholder !== null && $this->getEntityInstance()::hasColumn($whereOrColumn)) {
            $whereOrColumn = $this->getEntityInstance()::getFullColumnName($whereOrColumn);
        }

        if ($valueOrPlaceholder instanceof Entity) {
            $valueOrPlaceholder = $valueOrPlaceholder->getId();
        }

        return parent::where($whereOrColumn, $expression, $valueOrPlaceholder);
    }
}
