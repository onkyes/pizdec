<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use App\Exception\TranslatableHttpException;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
final class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function getById(int $id): User
    {
        $user = $this->find($id);

        if ($user === null) {
            throw new TranslatableHttpException(
                'user.not_found',
                Response::HTTP_NOT_FOUND,
            );
        }

        return $user;
    }

    /**
     * @return list<User>
     */
    public function findPaginated(int $limit, int $offset): array
    {
        return $this->findBy(
            [],
            ['id' => 'ASC'],
            $limit,
            $offset,
        );
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(\sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }
}
