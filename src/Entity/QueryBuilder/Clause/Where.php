<?php

declare(strict_types=1);

namespace JPI\ORM\Entity\QueryBuilder\Clause;

use JPI\Database\Query\Clause\Where as BaseClass;
use JPI\ORM\Entity;
use JPI\ORM\Entity\QueryBuilder\WhereableTrait;

class Where extends BaseClass {
    use WhereableTrait;

    public function getEntityInstance(): Entity {
        return $this->query->getEntityInstance();
    }
}
