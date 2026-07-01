<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class UpdateBasketItemRequest
{
    public function __construct(
        #[Assert\NotNull(message: 'Количество обязательно')]
        #[Assert\Type('integer', message: 'Количество должно быть целым числом')]
        #[Assert\Positive(message: 'Количество должно быть больше 0')]
        public int $quantity,
    ) {}
}
