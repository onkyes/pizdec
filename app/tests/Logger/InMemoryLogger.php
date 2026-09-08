<?php

declare(strict_types=1);

namespace App\Tests\Logger;

use Psr\Log\AbstractLogger;

final class InMemoryLogger extends AbstractLogger
    // Тестовый логгер-шпион. сохраняет вызовы логгера в массив,
    // чтобы в тестах проверить, что листенер действительно сработал.
{
    /**
     * @var list<array{level: string, message: string, context: array<string, mixed>}>
     */
    private array $records = [];

    /**
     * @param array<string, mixed> $context
     */
    public function log($level, \Stringable|string $message, array $context = []): void
    {
        $this->records[] = [
            'level' => (string) $level,
            'message' => (string) $message,
            'context' => $context,
        ];
    }

    public function reset(): void
    {
        $this->records = [];
    }

    /**
     * @return list<array{level: string, message: string, context: array<string, mixed>}>
     */
    public function getRecords(): array
    {
        return $this->records;
    }
}
