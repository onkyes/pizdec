<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\BasketItemRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BasketItemRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_BASKET_PRODUCT', fields: ['basket', 'product'])]
// ^^ не даст создать один и тот же продукт в одну корзину 2мя строками
class BasketItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false)]
    private Basket $basket;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Product $product;

    #[ORM\Column]
    private int $quantity;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct(Basket $basket, Product $product, int $quantity)
    {
        if ($quantity < 1) {
            throw new \InvalidArgumentException('Quantity must be greater than 0.');
        }

        $this->basket = $basket;
        $this->product = $product;
        $this->quantity = $quantity;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): int
    {
        if ($this->id === null) {
            throw new \LogicException('Basket item id is not initialized.');
        }

        return $this->id;
    }

    public function getBasket(): Basket
    {
        return $this->basket;
    }

    public function setBasket(Basket $basket): static
    {
        $this->basket = $basket;

        return $this;
    }

    public function getProduct(): Product
    {
        return $this->product;
    }

    public function setProduct(Product $product): static
    {
        $this->product = $product;

        return $this;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): static
    {
        if ($quantity < 1) {
            throw new \InvalidArgumentException('Quantity must be greater than 0.');
        }

        $this->quantity = $quantity;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }
}
