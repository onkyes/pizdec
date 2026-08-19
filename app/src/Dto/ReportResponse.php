<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\Report;

final readonly class ReportResponse implements \JsonSerializable // дто ответа отчёта
{
    public function __construct(// данные, которые уйдут в json
        public int $id, // id отчёта
        public string $status, // статус отчёта
        public string $periodFrom, // начало периода
        public string $periodTo, // конец периода
        public ?string $filePath, // путь к файлу, если отчёт готов
        public ?string $errorMessage, // ошибка, если генерация упала
        public string $createdAt, // когда создали
        public string $updatedAt, // когда обновили
        public ?string $completedAt, // когда завершили, может быть null
    ) {}

    public static function fromEntity(Report $report): self // собираем response из энтити
    {
        return new self(
            id: $report->getId(), // id отчёта
            status: $report->getStatus()->value, // enum переводим в строку
            periodFrom: $report->getPeriodFrom()->format(DATE_ATOM), // дату начала в ISO формат
            periodTo: $report->getPeriodTo()->format(DATE_ATOM), // дату конца в ISO формат
            filePath: $report->getFilePath(), // путь к файлу
            errorMessage: $report->getErrorMessage(), // текст ошибки
            createdAt: $report->getCreatedAt()->format(DATE_ATOM), // дата создания
            updatedAt: $report->getUpdatedAt()->format(DATE_ATOM), // дата обновления
            completedAt: $report->getCompletedAt()?->format(DATE_ATOM), // дата завершения или null
        );
    }

    /**
     * @return array<string, int|string|null>
     */
    public function jsonSerialize(): array // превращаем дто в массив для джейсон
    {
        return get_object_vars($this);
    }
}
