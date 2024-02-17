<?php

declare(strict_types=1);

namespace JPI\ORM\Entity\QueryBuilder\Clause\Where;

use JPI\Database\Query\Clause\Where\OrCondition as BaseClass;
use JPI\ORM\Entity;
use JPI\ORM\Entity\QueryBuilder\WhereableTrait;

class OrCondition extends BaseClass {
    use WhereableTrait;

    public function getEntityInstance(): Entity {
        return $this->query->getEntityInstance();
    }
}
