<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class PaginationRequest
{
    public function __construct(
        #[Assert\Positive(message: 'pagination.page.positive')]
        public int $page = 1,
        #[Assert\Range(
            notInRangeMessage: 'pagination.limit.range',
            min: 1,
            max: 20,
        )]
        public int $limit = 20,
    ) {}

    public function getOffset(): int
    {
        return ($this->page - 1) * $this->limit;
    }
}
