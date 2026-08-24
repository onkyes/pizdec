<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\OutboxMessage;
use App\Message\GenerateReportMessage;
use App\Message\ReportCompletedMessage;
use App\Repository\OutboxMessageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(name: 'app:outbox:publish')] // команда отправки outbox-сообщений в RabbitMQ
final class PublishOutboxMessagesCommand extends Command
{
    public function __construct(
        private readonly OutboxMessageRepository $outboxMessageRepository, // репозиторий outbox-сообщений
        private readonly MessageBusInterface $messageBus, // messenger отправит сообщение в нужный transport
        private readonly EntityManagerInterface $em, // нужен, чтобы отметить сообщение отправленным
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'limit',
            null,
            InputOption::VALUE_REQUIRED,
            'How many outbox messages to publish.',
            100,
        ); // сколько сообщений обработать за один запуск
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $limit = (int) $input->getOption('limit'); // берём размер пачки

        $outboxMessages = $this->outboxMessageRepository->findUnpublished($limit);
        // достаём сообщения, которые ещё не отправляли

        foreach ($outboxMessages as $outboxMessage) {
            $message = $this->createMessage($outboxMessage);
            // собираем реальное Messenger-сообщение из outbox-записи

            $this->messageBus->dispatch($message);
            // отправляем сообщение в RabbitMQ через messenger routing

            $outboxMessage->markPublished();
            // если dispatch не упал, считаем сообщение отправленным

            $this->em->flush();
            // сохраняем publishedAt
        }

        return Command::SUCCESS;
    }

    private function createMessage(OutboxMessage $outboxMessage): object // собираем сообщение по классу
    {
        return match ($outboxMessage->getMessageClass()) {
            GenerateReportMessage::class => new GenerateReportMessage(
                $this->getIntPayloadValue($outboxMessage, 'reportId'),
            ),
            ReportCompletedMessage::class => new ReportCompletedMessage(
                $this->getIntPayloadValue($outboxMessage, 'reportId'),
            ),
            default => throw new \RuntimeException('Unsupported outbox message class.'),
        };
    }

    private function getIntPayloadValue(OutboxMessage $outboxMessage, string $key): int // достаём int из payload
    {
        $payload = $outboxMessage->getPayload(); // payload из json-поля

        if (!isset($payload[$key]) || !\is_int($payload[$key])) {
            throw new \RuntimeException('Invalid outbox message payload.');
        }

        return $payload[$key];
    }
}
