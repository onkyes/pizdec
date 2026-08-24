<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\OutboxMessage;
use App\Message\ReportCompletedMessage;
use App\Repository\BuyerOrderItemRepository;
use App\Repository\ReportRepository;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemOperator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class ReportGeneratorService
{
    public function __construct(
        private ReportRepository $reportRepository,
        private BuyerOrderItemRepository $buyerOrderItemRepository,
        #[Autowire(service: 'reports.storage')]
        private FilesystemOperator $reportsStorage,
        private EntityManagerInterface $em,
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
                    $row = [
                        'product_name' => $soldItem['productName'],
                        'price' => $soldItem['productPrice'],
                        'user' => [
                            'id' => $soldItem['userId'],
                        ],
                    ];

                    $line = json_encode($row, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) . "\n";
                    // готовим одну джейсонЛ-строку для товара

                    for ($i = 0; $i < $soldItem['quantity']; ++$i) {
                        // пишем строку столько раз, сколько единиц товара купили
                        $writtenBytes = fwrite($stream, $line);
                        // fwrite вернёт количество записанных байт или false, если запись не удалась

                        if ($writtenBytes === false || $writtenBytes !== \strlen($line)) {
                            throw new \RuntimeException('Failed to write report row.');
                            // если строка не записалась или записалась не полностью, валим генерацию отчёта
                        }
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

            $outboxMessage = OutboxMessage::create(
                ReportCompletedMessage::class,
                ['reportId' => $report->getId()],
            );

            $this->em->persist($outboxMessage);
            $this->em->flush();

        } catch (\Throwable $e) {
            $report->markFailed($e->getMessage());
            $this->em->flush();

            throw $e;
        }
    }
}
