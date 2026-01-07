<?php

declare(strict_types=1);

namespace JPI\ORM\Entity;

use JPI\Database\Query\Result\CollectionInterface;
use JPI\ORM\Entity;
use JPI\Utils\Collection as BaseCollection;

class Collection extends BaseCollection implements CollectionInterface {

    public function toArray(?Entity $parentEntity = null): array {
        $array = [];

        foreach ($this->items as $key => $item) {
            $array[$key] = $item->toArray($parentEntity);
        }

        return $array;
    }
}
