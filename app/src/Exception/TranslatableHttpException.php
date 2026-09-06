<?php

declare(strict_types=1);

namespace App\Exception;

final class TranslatableHttpException extends \RuntimeException
{
    /**
     * @param array<string, string|int|float> $translationParameters
     */
    public function __construct(
        private readonly string $translationKey,
        private readonly int $statusCode,
        private readonly array $translationParameters = [])
    {
        parent::__construct($translationKey);
    }

    public function getTranslationKey(): string
    {
        return $this->translationKey;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * @return array<string, string|int|float>
     */
    public function getTranslationParameters(): array
    {
        return $this->translationParameters;
    }
}
