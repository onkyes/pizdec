<?php

declare(strict_types=1);

namespace App\Service;

use App\Message\ReportCompletedMessage;
use App\Repository\BuyerOrderItemRepository;
use App\Repository\ReportRepository;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemOperator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class ReportGeneratorService
{
    public function __construct(
        private ReportRepository $reportRepository,
        private BuyerOrderItemRepository $buyerOrderItemRepository,
        #[Autowire(service: 'reports.storage')]
        private FilesystemOperator $reportsStorage,
        private EntityManagerInterface $em,
        private MessageBusInterface $messageBus,
    ) {}

    public function generate(int $reportId): void
    {
        $report = $this->reportRepository->find($reportId); // ищем отчёт

        if ($report === null) {
            throw new \RuntimeException(\sprintf('Report %d not found.', $reportId));
        }
        $report->markProcessing();
        $this->em->flush(); // чтобы сразу показать статус Processing во внешние апи

        try {
            $soldItems = $this->buyerOrderItemRepository->iterateSoldItemsForPeriod(
                $report->getPeriodFrom(),
                $report->getPeriodTo(),
            );

            $stream = fopen('php://temp/maxmemory:1048576', 'w+');

            if ($stream === false) {
                throw new \RuntimeException('Failed to open temporary report stream.');
            }

            try {
                foreach ($soldItems as $soldItem) {
                    $order = $soldItem->getBuyerOrder();
                    $user = $order->getOwner();

                    $row = [
                        'product_name' => $soldItem->getProductName(),
                        'price' => $soldItem->getProductPrice(),
                        'user' => [
                            'id' => $user->getId(),
                        ],
                    ];

                    for ($i = 0; $i < $soldItem->getQuantity(); ++$i) {
                        fwrite($stream, json_encode($row, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) . "\n");
                    }
                }

                rewind($stream);

                $filePath = 'report-' . $report->getId() . '.jsonl';
                $this->reportsStorage->writeStream($filePath, $stream);
            } finally {
                fclose($stream);
            }

            $report->markCompleted($filePath);
            $this->em->flush();

            $this->messageBus->dispatch(new ReportCompletedMessage($report->getId()));

        } catch (\Throwable $e) {
            $report->markFailed($e->getMessage());
            $this->em->flush();

            throw $e;
        }
    }
}
