<?php

declare(strict_types=1);

namespace JPI\ORM\Entity\QueryBuilder;

use JPI\Database\Query\Builder;
use JPI\ORM\Entity;
use JPI\ORM\Entity\Collection as EntityCollection;
use Stringable;

/**
 * Convert the column to full column name if it's a column name, and allow passing entity collection or entity as value.
 */
trait WhereableTrait {

    abstract public function getEntityInstance(): Entity;

    public function where(
        Stringable|string $whereOrColumn,
        ?string $expression = null,
        Builder|EntityCollection|Entity|Stringable|string|int|float|array|null $valueOrPlaceholder = null
    ): static {
        if ($expression !== null && $valueOrPlaceholder !== null && $this->getEntityInstance()::hasColumn($whereOrColumn)) {
            $whereOrColumn = $this->getEntityInstance()::getFullColumnName($whereOrColumn);
        }

        if ($valueOrPlaceholder instanceof EntityCollection) {
            $valueOrPlaceholder = $valueOrPlaceholder->pluck("id")->toArray();
        }
        else if ($valueOrPlaceholder instanceof Entity) {
            $valueOrPlaceholder = $valueOrPlaceholder->getId();
        }

        return parent::where($whereOrColumn, $expression, $valueOrPlaceholder);
    }
}
