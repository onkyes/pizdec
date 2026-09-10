<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\PaginationRequest;
use App\Dto\UpdateUserRoleRequest;
use App\Entity\User;
use App\Enum\Role;
use App\Exception\TranslatableHttpException;
use App\Repository\UserRepository;
use App\Service\UserListProvider;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

final class AdminUserController extends AbstractController
{
    #[Route('/api/admin/users', name: 'admin_user_list', methods: ['GET'])]
    public function list(
        #[MapQueryString]
        PaginationRequest $pagination,
        UserListProvider $userListProvider,
    ): JsonResponse {
        $data = $userListProvider->getUserList($pagination);

        return $this->json($data, Response::HTTP_OK);
    }

    #[Route('/api/admin/users/{id}/role', name: 'admin_user_update_role', requirements: ['id' => '\d+'], methods: ['PATCH'])]
    public function updateRole(
        int $id,
        #[MapRequestPayload]
        UpdateUserRoleRequest $dto,
        UserRepository $repository,
        EntityManagerInterface $em,
    ): JsonResponse {
        $currentUser = $this->getUser();

        if (!$currentUser instanceof User) {
            throw new TranslatableHttpException('auth.required', Response::HTTP_UNAUTHORIZED);
        }

        $user = $repository->getById($id);

        if ($user->getId() === $currentUser->getId() && $dto->role === Role::User->value) {
            throw new TranslatableHttpException('user.cannot_demote_self', Response::HTTP_FORBIDDEN);
        }

        $user->setRoles([$dto->role]);

        $em->flush();

        return $this->json($this->serializeUser($user), Response::HTTP_OK);
    }

    /**
     * @return array{id: int, email: string, roles: list<string>}
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
