<?php

declare(strict_types=1);

namespace JPI\ORM\Entity;

use JPI\Database\Query\Result\CollectionInterface;
use JPI\Utils\Collection as BaseCollection;

class Collection extends BaseCollection implements CollectionInterface {

    public function toArray(): array {
        $array = [];

        foreach ($this->items as $key => $item) {
            $array[$key] = $item->toArray();
        }

        return $array;
    }
}
