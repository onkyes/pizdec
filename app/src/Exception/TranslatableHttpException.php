<?php

declare(strict_types=1);

namespace App\Exception;

final class TranslatableHttpException extends \RuntimeException
{
    public function __construct(
        private readonly string $translationKey,
        private readonly int $statusCode,
    ) {
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
}
