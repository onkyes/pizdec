<?php

declare(strict_types=1);

namespace App\Dto;

use Nelmio\ApiDocBundle\Attribute\Ignore;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class PaginationRequest
{
    public function __construct(
        #[OA\Property(
            description: 'Номер страницы, начиная с 1',
            type: 'integer',
            example: 1,
            default: 1,
            minimum: 1,
        )]
        #[Assert\Positive(message: 'pagination.page.positive')]
        public int $page = 1,
        #[OA\Property(
            description: 'Количество товаров на странице',
            type: 'integer',
            example: 20,
            default: 20,
            maximum: 20,
            minimum: 1,
        )]
        #[Assert\Range(
            notInRangeMessage: 'pagination.limit.range',
            min: 1,
            max: 20,
        )]
        public int $limit = 20,
    ) {}

    #[Ignore]
    public function getOffset(): int
    {
        return ($this->page - 1) * $this->limit;
    }
}
