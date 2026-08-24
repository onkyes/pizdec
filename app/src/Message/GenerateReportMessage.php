<?php

declare(strict_types=1);

namespace App\Message;

final readonly class GenerateReportMessage
{
    public function __construct(
        public int $reportId,
    ) {}
}
