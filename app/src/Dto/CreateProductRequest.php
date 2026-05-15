<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateProductRequest
{
        #[Assert\NotBlank(message: 'Название не может быть пустым')]
        #[Assert\Type('string')]
        public string $name;

        #[Assert\NotBlank(message: 'Описание не может быть пустым')]
        #[Assert\Type('string')]
        public string $description;

        #[Assert\NotNull]
        #[Assert\Type('integer', message: 'Цена должна быть целым числом')]
        #[Assert\PositiveOrZero(message: 'Цена должна быть больше или равна 0')]
        public int $price;

        #[Assert\NotNull]
        #[Assert\Type('integer', message: 'Масса должна быть целым числом')]
        #[Assert\PositiveOrZero(message: 'Масса должна быть больше или равна 0')]
        public int $weight;

        #[Assert\NotBlank(message: 'Категория не может быть пустой')]
        #[Assert\Type('string')]
        public string $category;
    public function __construct(
            string $name,
            string $description,
            int $price,
            int $weight,
            string $category
            )
        {
        $this->name = trim($name);
        $this->description = trim($description);
        $this->price = $price;
        $this->weight = $weight;
        $this->category = trim($category);
    }
}
