<?php

declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::REQUEST, priority: 16)]
final readonly class LocaleListener
{
    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $locale = $request->getPreferredLanguage(['ru', 'en']);

        if ($locale === null) {
            return;
        }

        $request->setLocale($locale);
    }
}
