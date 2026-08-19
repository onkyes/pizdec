<?php

declare(strict_types=1);

namespace App\Scheduler;

use App\Entity\Report;
use App\Message\GenerateReportMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Scheduler\Attribute\AsCronTask;

#[AsCronTask('0 2 * * *', timezone: 'Europe/Moscow')] // каждый день в 02:00
final readonly class GenerateDailyReportTask // задача ежедневного отчёта
{
    public function __construct(// зависимости задачи
        private EntityManagerInterface $em, // сохраняем отчёт в бд
        private MessageBusInterface $messageBus, // отправляем задачу в messenger
    ) {}

    public function __invoke(): void // этот метод вызовет scheduler
    {
        $periodFrom = new \DateTimeImmutable('yesterday 00:00:00'); // начало вчерашнего дня
        $periodTo = new \DateTimeImmutable('today 00:00:00'); // начало сегодняшнего дня

        $report = new Report($periodFrom, $periodTo); // создаём отчёт pending

        $this->em->persist($report); // готовим отчёт к сохранению
        $this->em->flush(); // сохраняем, чтобы появился id

        $this->messageBus->dispatch(new GenerateReportMessage($report->getId())); // кидаем генерацию в очередь
    }
}
