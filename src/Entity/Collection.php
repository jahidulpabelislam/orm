<?php

declare(strict_types=1);

namespace JPI\ORM\Entity;

use JPI\Database\Query\Result\CollectionInterface;
use JPI\ORM\Entity;
use JPI\Utils\Collection as BaseCollection;

class Collection extends BaseCollection implements CollectionInterface {

    use EagerLoadable;

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

        static::eagerLoadRelationships($this->items, $relations, $entityClass);

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
