<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Event\UserRegisteredEvent;
use App\Exception\TranslatableHttpException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final readonly class RegistrationService
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher,
        private EntityManagerInterface $em,
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    public function register(string $email, string $plainPassword): User
    {
        $user = new User($email, '', ['ROLE_USER']);

        $hashedPassword = $this->passwordHasher->hashPassword($user, $plainPassword);

        $user->setPassword($hashedPassword);

        $this->em->persist($user);

        try {
            $this->em->flush();
        } catch (UniqueConstraintViolationException) {
            throw new TranslatableHttpException(
                'user.email_exists',
                Response::HTTP_CONFLICT,
            );
        }

        $this->eventDispatcher->dispatch(
            new UserRegisteredEvent(
                $user->getId(),
                $user->getEmail(),
            ),
        );

        return $user;
    }
}

