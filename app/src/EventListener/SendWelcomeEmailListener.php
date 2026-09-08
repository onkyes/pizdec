<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Event\UserRegisteredEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: UserRegisteredEvent::class)]
final readonly class SendWelcomeEmailListener
// заглушка отправки приветственного сообщения.
{
    public function __construct(
        private LoggerInterface $logger,
    ) {}

    public function __invoke(UserRegisteredEvent $event): void
    {
        $this->logger->info('welcome_email.sent', [
            'userId' => $event->userId,
            'email' => $event->email,
        ]);
    }
}
