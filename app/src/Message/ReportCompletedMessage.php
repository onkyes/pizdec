<?php

declare(strict_types=1);

namespace App\Message;

final readonly class ReportCompletedMessage
{
    public function __construct(
        public int $reportId,
    ) {}
}
