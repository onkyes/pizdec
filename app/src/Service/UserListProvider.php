<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\PaginationRequest;
use App\Entity\User;
use App\Repository\UserRepository;

/**
 * @phpstan-type UserListItemData array{id: int, email: string, roles: list<string>}
 */
final readonly class UserListProvider
{
    public function __construct(
        private UserRepository $userRepository,
    ) {}

    /**
     * @return list<UserListItemData>
     */
    public function getUserList(PaginationRequest $pagination): array
    {
        $users = $this->userRepository->findPaginated(
            $pagination->limit,
            $pagination->getOffset(),
        );

        $data = [];

        foreach ($users as $user) {
            $data[] = $this->serializeUser($user);
        }

        return $data;
    }

    /**
     * @return UserListItemData
     */
    private function serializeUser(User $user): array
    {
        return [
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'roles' => $user->getRoles(),
        ];
    }
}
