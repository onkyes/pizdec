<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class PaginationRequest
{
    public function __construct(
        #[Assert\Positive(message: 'Page must be greater than 0')]
        public readonly int $page = 1,

        #[Assert\Range(
            notInRangeMessage: 'Limit must be between {{ min }} and {{ max }}',
            min: 1,
            max: 20
        )]
        public readonly int $limit = 20,
    ) {
    }

    public function getOffset(): int
    {
        return (($this->page - 1) * $this->limit);
    }
}
