<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\RegisterRequest;
use App\Entity\User;
use App\Event\UserRegisteredEvent;
use App\Exception\TranslatableHttpException;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class RegistrationController extends AbstractController
{
    #[Route('/api/register', name: 'user_register', methods: ['POST'])]
    public function register(
        #[MapRequestPayload]
        RegisterRequest $dto,
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $em,
        EventDispatcherInterface $eventDispatcher,
    ): JsonResponse {
        if ($userRepository->findOneBy(['email' => $dto->email]) !== null) {
            throw new TranslatableHttpException(
                'user.email_exists',
                Response::HTTP_CONFLICT,
            );
        }

        $user = new User($dto->email, '', ['ROLE_USER']);
        $hashedPassword = $passwordHasher->hashPassword($user, $dto->password);
        $user->setPassword($hashedPassword);

        $em->persist($user);
        $em->flush();

        $eventDispatcher->dispatch(
            new UserRegisteredEvent($user->getId(), $user->getEmail()),
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
