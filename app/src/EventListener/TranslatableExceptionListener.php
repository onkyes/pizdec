<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Exception\TranslatableHttpException;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsEventListener(event: KernelEvents::EXCEPTION)]
final readonly class TranslatableExceptionListener
{
    public function __construct(
        private TranslatorInterface $translator,
    ) {}

    public function __invoke(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        if (!$exception instanceof TranslatableHttpException) {
            return;
        }
        $message = $this->translator->trans(
            $exception->getTranslationKey(),
            locale: $event->getRequest()->getLocale(),
        );

        $response = new JsonResponse(
            ['message' => $message],
            $exception->getStatusCode(),
        );
        $event->setResponse($response);
    }
}
