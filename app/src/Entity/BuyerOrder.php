<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\DeliveryType;
use App\Enum\OrderStatus;
use App\Repository\BuyerOrderRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BuyerOrderRepository::class)]
class BuyerOrder
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    /**
     * @var Collection<int, BuyerOrderItem>
     */
    #[ORM\OneToMany(targetEntity: BuyerOrderItem::class, mappedBy: 'buyerOrder', orphanRemoval: true)]
    private Collection $items;

    public function __construct(
        #[ORM\ManyToOne(inversedBy: 'buyerOrders')]
        #[ORM\JoinColumn(nullable: false)]
        private User $owner,
        #[ORM\Column(type: 'string', length: 50, enumType: OrderStatus::class)]
        private OrderStatus $status,
        #[ORM\Column]
        private int $total,
        #[ORM\Column(type: 'string', length: 20, enumType: DeliveryType::class)]
        private DeliveryType $deliveryType, // тип получения заказа: pickup или courier

        #[ORM\Column(length: 255, nullable: true)]
        private ?string $deliveryRegion, // область нужна только для courier

        #[ORM\Column(length: 255, nullable: true)]
        private ?string $deliveryCity, // город нужен только для courier

        #[ORM\Column(length: 255, nullable: true)]
        private ?string $deliveryStreet, // улица нужна только для courier

        #[ORM\Column(length: 50, nullable: true)]
        private ?string $deliveryHouse, // дом нужен только для courier

        #[ORM\Column(length: 20, nullable: true)]
        private ?string $deliveryPostalCode, // индекс нужен только для courier

        #[ORM\Column(length: 50, nullable: true)]
        private ?string $deliveryEntrance = null, // подъезд может быть null

        #[ORM\Column(length: 50, nullable: true)]
        private ?string $deliveryApartment = null, // квартира может быть null
    ) {
        if ($total < 0) {
            // сумма заказа больше 0
            throw new \InvalidArgumentException('Сумма должна быть больше или равна 0.');
        }

        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->items = new ArrayCollection();
    }

    public function getId(): int
    {
        if ($this->id === null) {
            throw new \LogicException('Buyer order id is not initialized.');
        }

        return $this->id;
    }

    public function getOwner(): User
    {
        return $this->owner;
    }

    public function setOwner(User $owner): static
    {
        $this->owner = $owner;

        return $this;
    }

    public function getStatus(): OrderStatus
    {
        return $this->status;
    }

    public function setStatus(OrderStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getTotal(): int
    {
        return $this->total;
    }

    public function setTotal(int $total): static
    {
        if ($total < 0) {
            throw new \InvalidArgumentException('Total must be greater than or equal to 0.');
        }

        $this->total = $total;

        return $this;
    }

    public function getDeliveryType(): DeliveryType
    {
        return $this->deliveryType;
    }

    public function setDeliveryType(DeliveryType $deliveryType): static
    {
        $this->deliveryType = $deliveryType;

        return $this;
    }

    public function getDeliveryRegion(): ?string
    {
        return $this->deliveryRegion;
    }

    public function setDeliveryRegion(?string $deliveryRegion): static
    {
        $this->deliveryRegion = $deliveryRegion;

        return $this;
    }

    public function getDeliveryCity(): ?string
    {
        return $this->deliveryCity;
    }

    public function setDeliveryCity(?string $deliveryCity): static
    {
        $this->deliveryCity = $deliveryCity;

        return $this;
    }

    public function getDeliveryStreet(): ?string
    {
        return $this->deliveryStreet;
    }

    public function setDeliveryStreet(?string $deliveryStreet): static
    {
        $this->deliveryStreet = $deliveryStreet;

        return $this;
    }

    public function getDeliveryHouse(): ?string
    {
        return $this->deliveryHouse;
    }

    public function setDeliveryHouse(?string $deliveryHouse): static
    {
        $this->deliveryHouse = $deliveryHouse;

        return $this;
    }

    public function getDeliveryEntrance(): ?string
    {
        return $this->deliveryEntrance;
    }

    public function setDeliveryEntrance(?string $deliveryEntrance): static
    {
        $this->deliveryEntrance = $deliveryEntrance;

        return $this;
    }

    public function getDeliveryApartment(): ?string
    {
        return $this->deliveryApartment;
    }

    public function setDeliveryApartment(?string $deliveryApartment): static
    {
        $this->deliveryApartment = $deliveryApartment;

        return $this;
    }

    public function getDeliveryPostalCode(): ?string
    {
        return $this->deliveryPostalCode;
    }

    public function setDeliveryPostalCode(?string $deliveryPostalCode): static
    {
        $this->deliveryPostalCode = $deliveryPostalCode;

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

    /**
     * @return Collection<int, BuyerOrderItem>
     */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(BuyerOrderItem $item): static
    {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
            $item->setBuyerOrder($this);
        }

        return $this;
    }

    public function removeItem(BuyerOrderItem $item): static
    {
        $this->items->removeElement($item);

        return $this;
    }
}
