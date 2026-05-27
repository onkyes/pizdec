<?php

namespace App\Tests;

use App\Entity\Product;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class ProductAccessTest extends WebTestCase
{

    public function testLoginSuccess(): void
    {
        $client = static::createClient(); // тест-клиент

        $email = 'admin_' . uniqid() . '@example.com';
        // уникальное имя
        $password = 'password';

        $this->createUser($email, $password, ['ROLE_ADMIN']);
// создание пользователя в тест бд, пас захеширован
        $client->request(
            'POST', // пост запрос
            '/api/login_check',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'], //тело запроса json
            json_encode([
                'email' => $email,
                'password' => $password, // обычный пароль
            ], JSON_THROW_ON_ERROR)
        );

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        // проверка на логин

        $data = $this->decodeResponse($client);
        // декодируем json  в пхп массив

        self::assertArrayHasKey('token', $data);
        //Проверяет, что в массиве $data есть ключ 'token'
        self::assertIsString($data['token']);
        // проверка на строку
        self::assertNotEmpty($data['token']);
        // проверка на пустую строку
    }

    public function testLoginError(): void
    {
        $client = static::createClient(); // тест-клиент

        $email = 'user_' . uniqid() . '@example.com'; // уникальный email
        $password = 'password'; // правильный пароль, который будет сохранён в БД

        $this->createUser($email, $password, ['ROLE_USER']);
        // создаём пользователя в тестовой БД с верным паролем

        $client->request(
            'POST',
            '/api/login_check',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            \json_encode([
                'email' => $email,
                'password' => 'wrong-password',
                //  отправляем неправильный пароль
            ], \JSON_THROW_ON_ERROR)
        );

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        // ждём 401, потому что логин/пароль не совпали
    }

    public function testGuestCanRead(): void
    {
        $client = static::createClient(); // тест-клиент

        $client->request('GET', '/api/products');
        // ^^ гость без токена делает гет запрос на список продуктов
        // Authorization не передаём,знач пользователь считается гест(гость)

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        // проверяем, что доступ разрешён и вернулся 200 ОК

        $data = $this->decodeResponse($client);
        // декодируем json ответ в пхп массив

        self::assertIsArray($data);
        // проверяем, что список продуктов вернулся массивом
    }

    public function testGuestCannotCreate(): void
    {
        $client = static::createClient(); // тест-клиент

        $client->request(
            'POST',
            '/api/products', // гость без токена пытается создать продукт
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'], // тело запроса json
            \json_encode([
                'name' => 'Guest product',
                'description' => 'Guest description',
                'price' => 100,
                'weight' => 200,
                'category' => 'guest',
            ], \JSON_THROW_ON_ERROR)
        );

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        // проверяем, что без токена создавать нельзя, получаем 401
    }

    public function testUserCannotCreate(): void
    {
        $client = static::createClient(); // тест-клиент

        $headers = $this->authHeaders($client, 'ROLE_USER');

        $client->request(
            'POST',
            '/api/products', // обычный юзер пытается создать продукт
            [],
            [],
            $headers,
            \json_encode([
                'name' => 'User product',
                'description' => 'User description',
                'price' => 100,
                'weight' => 200,
                'category' => 'user',
            ], \JSON_THROW_ON_ERROR)
        );

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        // проверяем, что юзер авторизован, но прав на создание нет, получаем 403
    }

    public function testAdminCanCreate(): void
    {
        $client = static::createClient();

        $headers = $this->authHeaders($client, 'ROLE_ADMIN');

        $client->request(
            'POST',
            '/api/products', // админ пытается создать продукт
            [],
            [],
            $headers,
            json_encode([
                'name' => 'Admin product',
                'description' => 'Admin description',
                'price' => 100,
                'weight' => 200,
                'category' => 'admin',
            ], \JSON_THROW_ON_ERROR)
        );

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        // проверяем, что админ может создать продукт, получаем 201

        $data = $this->decodeResponse($client);
        // декодируем json ответ в пхп массив

        self::assertSame('Admin product', $data['name']);
        // проверяем, что создался продукт с нужным именем
    }

    public function testGuestCannotUpdate(): void
    {
        $client = static::createClient(); // тест-клиент

        $product = $this->createProduct();
        // создаём продукт в тестовой БД, чтобы было что обновлять

        $client->request(
            'PATCH',
            '/api/products/' . $product->getId(), // гость без токена пытается обновить продукт
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            \json_encode([
                'name' => 'Guest updated product',
            ], \JSON_THROW_ON_ERROR)
        );

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        // без токена обновлять нельзя, получаем 401
    }

    public function testUserCannotUpdate(): void
    {
        $client = static::createClient(); // тест-клиент

        $product = $this->createProduct();
        // создаём продукт в тестовой БД, чтобы было что обновлять

        $headers = $this->authHeaders($client, 'ROLE_USER');

        $client->request(
            'PATCH',
            '/api/products/' . $product->getId(), // обычный юзер пытается обновить продукт
            [],
            [],
            $headers,
            \json_encode([
                'name' => 'User updated product',
            ], \JSON_THROW_ON_ERROR)
        );

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        // проверяем, что юзер авторизован, но прав на обновление нет, получаем 403
    }

    public function testAdminCanUpdate(): void
    {
        $client = static::createClient(); // клиент

        $product = $this->createProduct(); // создаём продукт

        $headers = $this->authHeaders($client, 'ROLE_ADMIN');

        $client->request(
            'PATCH',
            '/api/products/' . $product->getId(),// админ обновляет продукт
            [],
            [],
            $headers,
            \json_encode([
                'name' => 'Admin updated product',
                'price' => 300,
            ], \JSON_THROW_ON_ERROR)
        );

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        // проверяем, что админ может обновить продукт, получаем 200

        $data = $this->decodeResponse($client);
        // декодируем json ответ в пхп массив


        self::assertSame($product->getId(), $data['id']);
        // проверяем, что обновился именно тот продукт

        self::assertSame('Admin updated product', $data['name']);
        // проверяем, что имя изменилось

        self::assertSame(300, ($data['price']));
        // проверяем, что цена изменилась

    }

    public function testGuestCannotDelete(): void
    {
        $client = static::createClient();

        $product = $this->createProduct();

        $client->request(
            'DELETE',
            '/api/products/' . $product->getId(),
            // гость без токена пытается удалить продукт
        );

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testUserCannotDelete(): void
    {
        $client = static::createClient();

        $product = $this->createProduct();
        // создаём продукт в тестовой БД, чтобы было что удалять

        $headers = $this->authHeaders($client, 'ROLE_USER');

        $client->request(
            'DELETE',
            '/api/products/' . $product->getId(),
            [],
            [],
            $headers
        );

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        // проверяем, что юзер авторизован, но прав на удаление нет, получаем 403

    }

    public function testAdminCanDelete(): void
    {
        $client = static::createClient();// тест-клиент

        $product = $this->createProduct();
        // создаём продукт в тестовой БД, чтобы было что удалять

        $productId = $product->getId();
        // сохраняем id отдельно, потому что после удаления объект уже нельзя будет нормально искать как существующий

        $headers = $this->authHeaders($client, 'ROLE_ADMIN');

        $client->request(
            'DELETE',
            '/api/products/' . $productId,
            [],
            [],
            $headers,
        );

        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        // проверяем, что удаление прошло успешно, получаем 204

        $client->request('GET', '/api/products/' . $productId);
        // пробуем получить удалённый продукт

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        // проверяем, что продукт реально удалён и больше не находится
    }

    private function createUser(string $email, string $plainPassword, array $roles): User
    {
        $user = new User($email, '', $roles);

        $passwordHasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $hashedPassword = $passwordHasher->hashPassword($user, $plainPassword);

        $user->setPassword($hashedPassword);

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->persist($user);
        $em->flush();

        return $user;
    }

    private function decodeResponse(KernelBrowser $client): array
    {
        return json_decode(
            $client->getResponse()->getContent(),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
    }

    private function getToken(KernelBrowser $client, string $email, string $password): string
    {
        $client->request(
            'POST',
            '/api/login_check',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            \json_encode([
                'email' => $email,
                'password' => $password,
            ], \JSON_THROW_ON_ERROR)
        );

        self::assertResponseStatusCodeSame(Response::HTTP_OK);

        $data = $this->decodeResponse($client);

        return $data['token'];
    }

    private function createProduct(): Product
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        // берём entity manager, чтобы сохранить продукт в тестовую БД

        $product = new Product(
            'Тестовый продукт',
            'Тестовое описание',
            100,
            500,
            'test'
        );

        $em->persist($product);
        // говорим doctrine, что продукт надо сохранить

        $em->flush();
        // реально записываем продукт в БД

        return $product;
        // возвращаем продукт, чтобы взять его id в тесте
    }

    private function authHeaders(KernelBrowser $client, string $role): array
    {
        $email = strtolower(str_replace(
            'ROLE_', '', $role))
            . '_'
            . \uniqid()
            . '@example.com';

        $password = 'password';

        $this->createUser($email, $password, [$role]);

        $token = $this->getToken($client, $email, $password);

        return [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ];
    }
}
