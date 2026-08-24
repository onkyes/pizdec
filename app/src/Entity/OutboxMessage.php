<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\OutboxMessageRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OutboxMessageRepository::class)]
class OutboxMessage // сообщение, которое надо позже отправить в ребит
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null; // id outbox-сообщения

    #[ORM\Column(length: 255)]
    private string $messageClass; // класс сообщения, которое надо отправить

    /**
     * @var array<string, mixed>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $payload; // данные для будущего сообщения

    #[ORM\Column]
    private \DateTimeImmutable $createdAt; // когда создали outbox-сообщение

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $publishedAt = null; // когда успешно отправили, null если ещё не отправили

    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(string $messageClass, array $payload)
    {
        $this->messageClass = $messageClass; // запоминаем класс сообщения
        $this->payload = $payload; // запоминаем payload
        $this->createdAt = new \DateTimeImmutable(); // фиксируем дату создания
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function create(string $messageClass, array $payload): self // создать outbox-сообщение
    {
        return new self($messageClass, $payload);
    }

    public function getId(): int // получить id
    {
        if ($this->id === null) {
            throw new \LogicException('Outbox message id is not initialized.');
        }

        return $this->id;
    }

    public function getMessageClass(): string // получить класс сообщения
    {
        return $this->messageClass;
    }

    /**
     * @return array<string, mixed>
     */
    public function getPayload(): array // получить payload
    {
        return $this->payload;
    }

    public function getCreatedAt(): \DateTimeImmutable // получить дату создания
    {
        return $this->createdAt;
    }

    public function getPublishedAt(): ?\DateTimeImmutable // получить дату отправки
    {
        return $this->publishedAt;
    }

    public function markPublished(): void // отметить сообщение как отправленное
    {
        $this->publishedAt = new \DateTimeImmutable();
    }
}
