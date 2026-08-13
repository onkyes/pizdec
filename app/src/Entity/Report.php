<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\ReportStatus;
use App\Repository\ReportRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ReportRepository::class)]
class Report
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 20, enumType: ReportStatus::class)]
    private ReportStatus $status;

    #[ORM\Column]
    private \DateTimeImmutable $periodFrom;

    #[ORM\Column]
    private \DateTimeImmutable $periodTo;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $filePath = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $errorMessage = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $completedAt = null;

    public function __construct(
        \DateTimeImmutable $periodFrom,
        \DateTimeImmutable $periodTo,
    ) {
        if ($periodFrom > $periodTo) {
            throw new \InvalidArgumentException('Начало периода отчёта не может быть позже окончания.');
        }

        $this->periodFrom = $periodFrom;
        $this->periodTo = $periodTo;
        $this->status = ReportStatus::Pending;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): int
    {
        if ($this->id === null) {
            throw new \LogicException('Идентификатор отчёта ещё не был создан.');
        }

        return $this->id;
    }

    public function getStatus(): ReportStatus
    {
        return $this->status;
    }

    public function getPeriodFrom(): \DateTimeImmutable
    {
        return $this->periodFrom;
    }

    public function getPeriodTo(): \DateTimeImmutable
    {
        return $this->periodTo;
    }

    public function getFilePath(): ?string
    {
        return $this->filePath;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getCompletedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function markProcessing(): void
    {
        $this->status = ReportStatus::Processing;
        $this->touch();
    }

    public function markCompleted(string $filePath): void
    {
        $this->status = ReportStatus::Completed;
        $this->filePath = $filePath;
        $this->errorMessage = null;
        $this->completedAt = new \DateTimeImmutable();
        $this->touch();
    }

    public function markFailed(string $errorMessage): void
    {
        $this->status = ReportStatus::Failed;
        $this->errorMessage = $errorMessage;
        $this->completedAt = new \DateTimeImmutable();
        $this->touch();
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
