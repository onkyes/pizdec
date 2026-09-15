<?php

declare(strict_types=1);

namespace App\Dto;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class AddBasketItemRequest
{
    #[OA\Property(
        description: 'ID существующего товара',
        type: 'integer',
        example: 1,
        minimum: 1,
    )]
    #[Assert\NotNull(message: 'basket.product.required')]
    #[Assert\Type('integer', message: 'basket.product_id.integer')]
    #[Assert\Positive(message: 'basket.product_id.positive')]
    public int $productId;

    #[OA\Property(
        description: 'Количество единиц товара, которое нужно добавить',
        type: 'integer',
        example: 2,
        minimum: 1,
    )]
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
