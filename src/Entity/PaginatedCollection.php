<?php

declare(strict_types=1);

namespace JPI\ORM\Entity;

use JPI\Database\Query\Result\PaginatedCollectionInterface;
use JPI\Utils\Collection\PaginatedTrait;

class PaginatedCollection extends Collection implements PaginatedCollectionInterface {

    use PaginatedTrait;
}
