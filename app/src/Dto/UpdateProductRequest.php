<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class UpdateProductRequest
{
    #[Assert\Type('string')]
    #[Assert\NotBlank(message: 'Название не может быть пустым')]
    public readonly ?string $name;

    #[Assert\Type('string')]
    #[Assert\NotBlank(message: 'Описание не может быть пустым')]
    public readonly ?string $description;

    #[Assert\Type('integer', message: 'Цена должна быть целым числом')]
    #[Assert\PositiveOrZero(message: 'Цена должна быть больше или равна 0')]
    public readonly ?int $price;

    #[Assert\Type('integer', message: 'Масса должна быть целым числом')]
    #[Assert\PositiveOrZero(message: 'Масса должна быть больше или равна 0')]
    public readonly ?int $weight;

    #[Assert\Type('string')]
    #[Assert\NotBlank(message: 'Категория не может быть пустой')]
    public readonly ?string $category;

    public function __construct(
        ?string $name = null,
        ?string $description = null,
        ?int $price = null,
        ?int $weight = null,
        ?string $category = null,
    ) {
        $this->name = $name !== null ? trim($name) : null;
        $this->description = $description !== null ? trim($description) : null;
        $this->price = $price;
        $this->weight = $weight;
        $this->category = $category !== null ? trim($category) : null;
    }
}
