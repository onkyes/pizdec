<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\BasketRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BasketRepository::class)]
class Basket
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'basket')]
    #[ORM\JoinColumn(nullable: false)]
    private User $owner;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    /**
     * @var Collection<int, BasketItem>
     */
    #[ORM\OneToMany(targetEntity: BasketItem::class, mappedBy: 'basket', orphanRemoval: true)]
    private Collection $items;

    public function __construct(User $owner)
    {
        $this->owner = $owner; // корзина всегда создаётся для конкретного пользователя
        $this->createdAt = new \DateTimeImmutable(); // дата создания корзины
        $this->updatedAt = new \DateTimeImmutable(); // при создании дата обновления такая же
        $this->items = new ArrayCollection(); // коллекция товаров корзины, чтобы не работать с null
    }

    public function getId(): int
    {
        if ($this->id === null) {
            throw new \LogicException('Basket id is not initialized.');
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
     * @return Collection<int, BasketItem>
     */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(BasketItem $item): static
    {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
            $item->setBasket($this);
        }

        return $this;
    }

    public function removeItem(BasketItem $item): static
    {
        $this->items->removeElement($item);

        return $this;
    }
}
