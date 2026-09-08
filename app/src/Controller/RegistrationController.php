<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\RegisterRequest;
use App\Service\RegistrationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

final class RegistrationController extends AbstractController
{
    #[Route('/api/register', name: 'user_register', methods: ['POST'])]
    public function register(
        #[MapRequestPayload]
        RegisterRequest $dto,
        RegistrationService $registrationService,
    ): JsonResponse {
        $user = $registrationService->register(
            $dto->email,
            $dto->password,
        );

        return $this->json(
            [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'roles' => $user->getRoles(),
            ],
            Response::HTTP_CREATED,
        );
    }
}
