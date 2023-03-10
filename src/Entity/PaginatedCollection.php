<?php

declare(strict_types=1);

namespace JPI\ORM\Entity;

use JPI\Utils\Collection\PaginatedTrait;

class PaginatedCollection extends Collection {

    use PaginatedTrait;
}
