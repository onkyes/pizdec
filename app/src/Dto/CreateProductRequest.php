<?php


namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class CreateProductRequest
{
    public readonly string $name;
    public readonly string $description;
    public readonly int $price;
    public readonly int $weight;
    public readonly string $category;

    public function __construct(
        #[Assert\NotBlank(message: 'Название не может быть пустым')]
        #[Assert\Type('string')]
        string $name,

        #[Assert\NotBlank(message: 'Описание не может быть пустым')]
        #[Assert\Type('string')]
        string $description,

        #[Assert\NotNull]
        #[Assert\Type('integer', message: 'Цена должна быть целым числом')]
        #[Assert\PositiveOrZero(message: 'Цена должна быть больше или равна 0')]
        int $price,

        #[Assert\NotNull]
        #[Assert\Type('integer', message: 'Масса должна быть целым числом')]
        #[Assert\PositiveOrZero(message: 'Масса должна быть больше или равна 0')]
        int $weight,

        #[Assert\NotBlank(message: 'Категория не может быть пустой')]
        #[Assert\Type('string')]
        string $category,
    ) {
        $this->name = trim($name);
        $this->description = trim($description);
        $this->price = $price;
        $this->weight = $weight;
        $this->category = trim($category);
    }

}
