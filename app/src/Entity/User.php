<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users')]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private string $email;

    /**
     * @var list<string> The user roles
     */
    #[ORM\Column]
    private array $roles = [];

    /**
     * @var string The hashed password
     */
    #[ORM\Column]
    private string $password;

    #[ORM\OneToOne(mappedBy: 'owner')]
    private ?Basket $basket = null;

    /**
     * @var Collection<int, BuyerOrder>
     */
    #[ORM\OneToMany(targetEntity: BuyerOrder::class, mappedBy: 'owner')]
    private Collection $buyerOrders;

    /**
     * @param list<string> $roles
     */
    public function __construct(string $email, string $password, array $roles = [])
    {
        $this->email = $email;
        $this->roles = $roles;
        $this->password = $password;
        $this->buyerOrders = new ArrayCollection();
    }

    public function getId(): int
    {
        if ($this->id === null) {
            throw new \LogicException('User id is not initialized.');
        }

        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    /**
     * Ensure the session doesn't contain actual password hashes by CRC32C-hashing them, as supported since Symfony 7.3.
     */
    public function __serialize(): array
    {
        $data = (array) $this;
        $data["\0" . self::class . "\0password"] = hash('crc32c', $this->password);

        return $data;
    }

    public function getBasket(): ?Basket
    {
        return $this->basket;
    }

    public function setBasket(Basket $basket): static
    {
        // set the owning side of the relation if necessary
        if ($basket->getOwner() !== $this) {
            $basket->setOwner($this);
        }

        $this->basket = $basket;

        return $this;
    }

    /**
     * @return Collection<int, BuyerOrder>
     */
    public function getBuyerOrders(): Collection
    {
        return $this->buyerOrders;
    }

    public function addBuyerOrder(BuyerOrder $buyerOrder): static
    {
        if (!$this->buyerOrders->contains($buyerOrder)) {
            $this->buyerOrders->add($buyerOrder);
            $buyerOrder->setOwner($this);
        }

        return $this;
    }

    public function removeBuyerOrder(BuyerOrder $buyerOrder): static
    {
        $this->buyerOrders->removeElement($buyerOrder);

        return $this;
    }
}
