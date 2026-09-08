<?php

declare(strict_types=1);

namespace App\Event;

final readonly class UserRegisteredEvent
{
    public function __construct(
        public int $userId,
        public string $email,
    ) {}
}
