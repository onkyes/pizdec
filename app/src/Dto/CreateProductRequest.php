<?php

declare(strict_types=1);

namespace App\Dto;

use App\Enum\ProductCategory;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateProductRequest
{
    #[Assert\NotBlank(message: 'product.name.not_blank')]
    #[Assert\Type('string')]
    public string $name;

    #[Assert\NotBlank(message: 'product.description.not_blank')]
    #[Assert\Type('string')]
    public string $description;

    #[Assert\NotNull]
    #[Assert\Type('integer', message: 'product.price.integer')]
    #[Assert\PositiveOrZero(message: 'product.price.positive_or_zero')]
    public int $price;

    #[Assert\NotNull]
    #[Assert\Type('integer', message: 'product.weight.integer')]
    #[Assert\PositiveOrZero(message: 'product.weight.positive_or_zero')]
    public int $weight;

    #[Assert\NotBlank(message: 'product.category.not_blank')]
    #[Assert\Type('string')]
    #[Assert\Choice(
        callback: [ProductCategory::class, 'values'],
        message: 'product.category.choice',
    )]
    public string $category;

    public function __construct(
        string $name,
        string $description,
        int $price,
        int $weight,
        string $category,
    ) {
        $this->name = trim($name);
        $this->description = trim($description);
        $this->price = $price;
        $this->weight = $weight;
        $this->category = trim($category);
    }
}
