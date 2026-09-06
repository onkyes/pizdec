<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;
use Symfony\Component\Security\Http\Authorization\AccessDeniedHandlerInterface;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class ApiSecurityFailureHandler implements
    AuthenticationEntryPointInterface,
    AccessDeniedHandlerInterface,
    AuthenticationFailureHandlerInterface
{
    public function __construct(
        private TranslatorInterface $translator,
    ) {}

    /**
     * Вызывается, когда пользователь не авторизован.
     * Например, запрос пришёл без JWT.
     */
    public function start(
        Request $request,
        ?AuthenticationException $authException = null,
    ): Response {
        return $this->createResponse(
            $request,
            'auth.required',
            Response::HTTP_UNAUTHORIZED,
        );
    }

    /**
     * Вызывается, когда пользователь авторизован,
     * но у него недостаточно прав.
     */
    public function handle(
        Request $request,
        AccessDeniedException $accessDeniedException,
    ): ?Response {
        return $this->createResponse(
            $request,
            'auth.forbidden',
            Response::HTTP_FORBIDDEN,
        );
    }

    /**
     * Вызывается при неудачной попытке входа.
     */
    public function onAuthenticationFailure(
        Request $request,
        AuthenticationException $exception,
    ): Response {
        return $this->createResponse(
            $request,
            'auth.invalid_credentials',
            Response::HTTP_UNAUTHORIZED,
        );
    }

    private function createResponse(
        Request $request,
        string $translationKey,
        int $statusCode,
    ): JsonResponse {
        $message = $this->translator->trans(
            $translationKey,
            locale: $request->getLocale(),
        );

        return new JsonResponse(
            ['message' => $message],
            $statusCode,
        );
    }
}
