<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class AddBasketItemRequest
{
    public function __construct(
        #[Assert\NotNull(message: 'Товар обязателен')]
        #[Assert\Type('integer', message: 'ID товара должен быть целым числом')]
        #[Assert\Positive(message: 'ID товара должен быть больше 0')]
        public int $productId,
        #[Assert\NotNull(message: 'Количество обязательно')]
        #[Assert\Type('integer', message: 'Количество должно быть целым числом')]
        #[Assert\Positive(message: 'Количество должно быть больше 0')]
        public int $quantity,
    ) {}
}
