<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\GenerateReportMessage;
use App\Service\ReportGeneratorService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GenerateReportMessageHandler
{
    public function __construct(
        private ReportGeneratorService $reportGeneratorService,
    ) {}

    public function __invoke(GenerateReportMessage $message): void
    {
        $this->reportGeneratorService->generate($message->reportId);
    }
}
