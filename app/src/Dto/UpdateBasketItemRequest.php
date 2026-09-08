<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class UpdateBasketItemRequest
{
    public function __construct(
        #[Assert\NotNull(message: 'basket.quantity.required')]
        #[Assert\Type('integer', message: 'basket.quantity.integer')]
        #[Assert\Positive(message: 'basket.quantity.positive')]
        public int $quantity,
    ) {}
}
