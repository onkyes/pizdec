<?php

declare(strict_types=1);

namespace App\Tests;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class AdminUserControllerTest extends WebTestCase
{
    use TestHelper;

    public function testAdminCanListUsers(): void
    {
        $client = self::createClient();

        $headers = $this->authHeaders($client, 'ROLE_ADMIN');
        // создаём администратора и получаем его токен

        $this->createUser('list_user_' . uniqid() . '@example.com', 'password', ['ROLE_USER']);
        // добавляем ещё одного пользователя, чтобы список был не пустым

        $client->request('GET', '/api/admin/users', [], [], $headers);

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        // админ получает список пользователей, 200

        $data = $this->decodeResponse($client);

        self::assertGreaterThanOrEqual(2, \count($data));
        // в списке минимум админ и созданный пользователь

        $first = $data[0];
        self::assertArrayHasKey('id', $first);
        self::assertArrayHasKey('email', $first);
        self::assertArrayHasKey('roles', $first);
        self::assertArrayNotHasKey('password', $first);
        // пароли не возвращаются
    }

    public function testListUsersRespectsPagination(): void
    {
        $client = self::createClient();

        $headers = $this->authHeaders($client, 'ROLE_ADMIN');

        $client->request('GET', '/api/admin/users?page=1&limit=1', [], [], $headers);

        self::assertResponseStatusCodeSame(Response::HTTP_OK);

        $data = $this->decodeResponse($client);

        self::assertCount(1, $data);
        // лимит ограничивает выборку одной записью
    }

    public function testListUsersValidationError(): void
    {
        $client = self::createClient();

        $headers = $this->authHeaders($client, 'ROLE_ADMIN');
        $headers['HTTP_ACCEPT'] = 'application/json';
        $headers['HTTP_ACCEPT_LANGUAGE'] = 'ru';

        $client->request('GET', '/api/admin/users?page=0&limit=100', [], [], $headers);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        // MapQueryString по умолчанию отвечает 404 при ошибке валидации

        $data = $this->decodeResponse($client);

        $messagesByProperty = array_column(
            $data['violations'],
            'title',
            'propertyPath',
        );

        self::assertSame('Номер страницы должен быть больше 0', $messagesByProperty['page']);
        self::assertSame('Лимит должен быть от 1 до 20', $messagesByProperty['limit']);
    }

    public function testGuestCannotListUsers(): void
    {
        $client = self::createClient();

        $client->request(
            'GET',
            '/api/admin/users',
            [],
            [],
            ['HTTP_ACCEPT_LANGUAGE' => 'ru'],
        );

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        // без токена доступа нет, 401

        self::assertSame(
            ['message' => 'Требуется авторизация'],
            $this->decodeResponse($client),
        );
    }

    public function testUserCannotListUsers(): void
    {
        $client = self::createClient();

        $headers = $this->authHeaders($client, 'ROLE_USER');
        $headers['HTTP_ACCEPT_LANGUAGE'] = 'ru';

        $client->request('GET', '/api/admin/users', [], [], $headers);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        // обычный пользователь авторизован, но прав нет, 403

        self::assertSame(
            ['message' => 'Недостаточно прав'],
            $this->decodeResponse($client),
        );
    }

    public function testAdminCanPromoteUserToAdmin(): void
    {
        $client = self::createClient();

        $headers = $this->authHeaders($client, 'ROLE_ADMIN');

        $target = $this->createUser('promote_' . uniqid() . '@example.com', 'password', ['ROLE_USER']);
        $targetId = $target->getId();

        $client->request(
            'PATCH',
            '/api/admin/users/' . $targetId . '/role',
            [],
            [],
            $headers,
            json_encode(['role' => 'ROLE_ADMIN'], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_OK);

        $data = $this->decodeResponse($client);

        self::assertSame($targetId, $data['id']);
        self::assertContains('ROLE_ADMIN', $data['roles']);
        // роль изменилась в ответе

        $this->assertUserHasRole($targetId, 'ROLE_ADMIN');
        // и сохранилась в базе
    }

    public function testAdminCanDemoteAdminToUser(): void
    {
        $client = self::createClient();

        $headers = $this->authHeaders($client, 'ROLE_ADMIN');

        $target = $this->createUser('demote_' . uniqid() . '@example.com', 'password', ['ROLE_ADMIN']);
        $targetId = $target->getId();

        $client->request(
            'PATCH',
            '/api/admin/users/' . $targetId . '/role',
            [],
            [],
            $headers,
            json_encode(['role' => 'ROLE_USER'], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_OK);

        $data = $this->decodeResponse($client);

        self::assertNotContains('ROLE_ADMIN', $data['roles']);
        // роль администратора снята
    }

    public function testUpdateRoleInvalidValue(): void
    {
        $client = self::createClient();

        $headers = $this->authHeaders($client, 'ROLE_ADMIN');

        $target = $this->createUser('invalid_role_' . uniqid() . '@example.com', 'password', ['ROLE_USER']);

        $client->request(
            'PATCH',
            '/api/admin/users/' . $target->getId() . '/role',
            [],
            [],
            $headers,
            json_encode(['role' => 'ROLE_SUPERHERO'], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        // неизвестная роль отклоняется валидацией, 422
    }

    public function testUpdateRoleUserNotFound(): void
    {
        $client = self::createClient();

        $headers = $this->authHeaders($client, 'ROLE_ADMIN');
        $headers['HTTP_ACCEPT_LANGUAGE'] = 'ru';

        $client->request(
            'PATCH',
            '/api/admin/users/999999/role',
            [],
            [],
            $headers,
            json_encode(['role' => 'ROLE_ADMIN'], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        self::assertSame(
            ['message' => 'Пользователь не найден'],
            $this->decodeResponse($client),
        );
    }

    public function testUserCannotUpdateRole(): void
    {
        $client = self::createClient();

        $target = $this->createUser('victim_' . uniqid() . '@example.com', 'password', ['ROLE_USER']);

        $headers = $this->authHeaders($client, 'ROLE_USER');
        $headers['HTTP_ACCEPT_LANGUAGE'] = 'ru';

        $client->request(
            'PATCH',
            '/api/admin/users/' . $target->getId() . '/role',
            [],
            [],
            $headers,
            json_encode(['role' => 'ROLE_ADMIN'], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        // обычный пользователь не может менять роли, 403
    }

    private function assertUserHasRole(int $userId, string $role): void
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->clear();

        $user = $em->getRepository(User::class)->find($userId);

        self::assertInstanceOf(User::class, $user);
        self::assertContains($role, $user->getRoles());
    }
}
