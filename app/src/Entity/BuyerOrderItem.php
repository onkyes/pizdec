<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\BuyerOrderItemRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BuyerOrderItemRepository::class)]
class BuyerOrderItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        #[ORM\ManyToOne(inversedBy: 'items')]
        #[ORM\JoinColumn(nullable: false)]
        private BuyerOrder $buyerOrder,
        #[ORM\Column]
        private int $productId,
        #[ORM\Column(length: 255)]
        private string $productName,
        #[ORM\Column]
        private int $productPrice,
        #[ORM\Column]
        private int $productWeight,
        #[ORM\Column(length: 255)]
        private string $productCategory,
        #[ORM\Column]
        private int $quantity,
        #[ORM\Column]
        private int $lineTotal,
    ) {
        if ($quantity < 1) {
            throw new \InvalidArgumentException('Quantity must be greater than 0.');
        }

        if ($lineTotal < 0) {
            throw new \InvalidArgumentException('Line total must be greater than or equal to 0.');
        }

        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): int
    {
        if ($this->id === null) {
            throw new \LogicException('Buyer order item id is not initialized.');
        }

        return $this->id;
    }

    public function getBuyerOrder(): BuyerOrder
    {
        return $this->buyerOrder;
    }

    public function setBuyerOrder(BuyerOrder $buyerOrder): static
    {
        $this->buyerOrder = $buyerOrder;

        return $this;
    }

    public function getProductId(): int
    {
        return $this->productId;
    }

    public function setProductId(int $productId): static
    {
        $this->productId = $productId;

        return $this;
    }

    public function getProductName(): string
    {
        return $this->productName;
    }

    public function setProductName(string $productName): static
    {
        $this->productName = $productName;

        return $this;
    }

    public function getProductPrice(): int
    {
        return $this->productPrice;
    }

    public function setProductPrice(int $productPrice): static
    {
        $this->productPrice = $productPrice;

        return $this;
    }

    public function getProductWeight(): int
    {
        return $this->productWeight;
    }

    public function setProductWeight(int $productWeight): static
    {
        $this->productWeight = $productWeight;

        return $this;
    }

    public function getProductCategory(): string
    {
        return $this->productCategory;
    }

    public function setProductCategory(string $productCategory): static
    {
        $this->productCategory = $productCategory;

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

        return $this;
    }

    public function getLineTotal(): int
    {
        return $this->lineTotal;
    }

    public function setLineTotal(int $lineTotal): static
    {
        if ($lineTotal < 0) {
            throw new \InvalidArgumentException('Line total must be greater than or equal to 0.');
        }

        $this->lineTotal = $lineTotal;

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
}
