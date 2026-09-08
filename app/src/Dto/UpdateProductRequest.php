<?php

declare(strict_types=1);

namespace App\Dto;

use App\Enum\ProductCategory;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class UpdateProductRequest
{
    #[Assert\Type('string')]
    #[Assert\NotBlank(message: 'product.name.not_blank', allowNull: true)]
    public ?string $name;

    #[Assert\Type('string')]
    #[Assert\NotBlank(message: 'product.description.not_blank', allowNull: true)]
    public ?string $description;

    #[Assert\Type('integer', message: 'product.price.integer')]
    #[Assert\PositiveOrZero(message: 'product.price.positive_or_zero')]
    public ?int $price;

    #[Assert\Type('integer', message: 'product.weight.integer')]
    #[Assert\PositiveOrZero(message: 'product.weight.positive_or_zero')]
    public ?int $weight;

    #[Assert\Type('string')]
    #[Assert\NotBlank(message: 'product.category.not_blank', allowNull: true)]
    #[Assert\Choice(
        callback: [ProductCategory::class, 'values'], // продуктКатегори-енам
        message: 'product.category.choice',
    )]
    public ?string $category;

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
