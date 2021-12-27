<?php

namespace JPI\ORM\Entity;

use ArrayAccess;
use ArrayIterator;
use Countable;
use IteratorAggregate;
use JPI\ORM\Entity;

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
     * @param $entities \JPI\ORM\Entity[]
     * @param $totalCount int|null
     * @param $limit int|null
     * @param $page int|null
     */
    public function __construct(array $entities = [], int $totalCount = null, int $limit = null, int $page = null) {
        $this->entities = $entities;
        $this->count = count($entities);
        $this->totalCount = $totalCount ?? null;
        $this->limit = $limit;
        $this->page = $page;
    }

    /**
     * @return \JPI\ORM\Entity[]
     */
    public function getItems(): array {
        return $this->entities;
    }

    /**
     * @param $index string
     * @return bool
     */
    protected function isset($index): bool {
        return array_key_exists($index, $this->entities);
    }

    /**
     * @param $index string
     * @return \JPI\ORM\Entity|null
     */
    public function get($index): ?Entity {
        return $this->entities[$index] ?? null;
    }

    // ArrayAccess //

    /**
     * @param $index string
     * @return bool
     */
    public function offsetExists($index): bool {
        return $this->isset($index);
    }

    /**
     * @param $index string
     * @return \JPI\ORM\Entity|null
     */
    public function offsetGet($index) {
        return $this->get($index);
    }

    /**
     * @param $index string
     * @param $entity \JPI\ORM\Entity
     * @throws \Exception
     */
    public function offsetSet($index, $entity): void {
        throw new Exception("Updating is not allowed");
    }

    /**
     * @param $index string
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
