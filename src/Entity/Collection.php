<?php

namespace JPI\ORM\Entity;

use ArrayAccess;
use ArrayIterator;
use Countable;
use Exception;
use IteratorAggregate;
use JPI\ORM\Entity;

/**
 * Collection class for entities - mainly to hold additional meta data such as pagination limit & page value.
 *
 * @author Jahidul Pabel Islam <me@jahidulpabelislam.com>
 * @copyright 2012-2022 JPI
 */
class Collection implements ArrayAccess, Countable, IteratorAggregate {

    /**
     * @var \JPI\ORM\Entity[]
     */
    protected $entities;

    /**
     * @var int
     */
    protected $count;

    /**
     * @var int|null
     */
    protected $totalCount;

    /**
     * @var int|null
     */
    protected $limit;

    /**
     * @var int|null
     */
    protected $page;

    /**
     * @param \JPI\ORM\Entity[] $entities
     * @param int|null $totalCount
     * @param int|null $limit
     * @param int|null $page
     */
    public function __construct(array $entities = [], int $totalCount = null, int $limit = null, int $page = null) {
        $this->entities = $entities;
        $this->count = count($entities);
        $this->totalCount = $totalCount ?? null;
        $this->limit = $limit;
        $this->page = $page;
    }

    /**
     * @param string $index
     * @return bool
     */
    protected function isset($index): bool {
        return array_key_exists($index, $this->entities);
    }

    /**
     * @param string $index
     * @return \JPI\ORM\Entity|null
     */
    public function get($index): ?Entity {
        return $this->entities[$index] ?? null;
    }

    // ArrayAccess //

    /**
     * @param string $index
     * @return bool
     */
    public function offsetExists($index): bool {
        return $this->isset($index);
    }

    /**
     * @param string $index
     * @return \JPI\ORM\Entity|null
     */
    public function offsetGet($index): ?Entity {
        return $this->get($index);
    }

    /**
     * @param string $index
     * @param \JPI\ORM\Entity $entity
     * @return void
     * @throws \Exception
     */
    public function offsetSet($index, $entity): void {
        throw new Exception("Updating is not allowed");
    }

    /**
     * @param string $index
     * @return void
     * @throws \Exception
     */
    public function offsetUnset($index): void {
        throw new Exception("Updating is not allowed");
    }

    // IteratorAggregate //

    /**
     * @return \ArrayIterator
     */
    public function getIterator(): ArrayIterator {
        return new ArrayIterator($this->entities);
    }

    // Countable //

    /**
     * @return int
     */
    public function count(): int {
        return $this->count;
    }

    /**
     * @return int
     */
    public function getCount(): int {
        return $this->count();
    }

    /**
     * @return int
     */
    public function getTotalCount(): int {
        return $this->totalCount ?? $this->count();
    }

    /**
     * @return int|null
     */
    public function getLimit(): ?int {
        return $this->limit;
    }

    /**
     * @return int|null
     */
    public function getPage(): ?int {
        return $this->page;
    }
}
