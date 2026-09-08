<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class AddBasketItemRequest
{
    #[Assert\NotNull(message: 'basket.product.required')]
    #[Assert\Type('integer', message: 'basket.product_id.integer')]
    #[Assert\Positive(message: 'basket.product_id.positive')]
    public int $productId;

    #[Assert\NotNull(message: 'basket.quantity.required')]
    #[Assert\Type('integer', message: 'basket.quantity.integer')]
    #[Assert\Positive(message: 'basket.quantity.positive')]
    public int $quantity;

    public function __construct(
        int $productId,
        int $quantity,
    ) {
        $this->productId = $productId;
        $this->quantity = $quantity;
    }
}
