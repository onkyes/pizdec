<?php

namespace App\Tests;

use App\Entity\User;
use App\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class ProductControllerTest extends WebTestCase
{
    public function testListProductSuccess(): void
    {
        $client = static::createClient(); // создаём тестовый http клиент

        $client->request('GET', '/api/products'); // отправляем гет запрос на список продуктов

        self::assertResponseStatusCodeSame(Response::HTTP_OK); // ответ 200 OK

        $data = $this->decodeResponse($client); // декодируем json ответ в массив

        self::assertIsArray($data); // проверяем, что список продуктов вернулся массивом
    }
    public function testListProductsError(): void
    {
        $client = static::createClient(); // клиент

        $client->request('GET', '/api/products?limit=100');
        // отправляем запрос с невалидным limit

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testShowProductSuccess(): void
    {
        $client = static::createClient(); // создаём тестовый HTTP-клиент

        $product = $this->createProduct(); // создаём продукт в тестовой БД

        $client->request('GET', '/api/products/' . $product->getId()); // запрашиваем созданный продукт по его id

        self::assertResponseStatusCodeSame(Response::HTTP_OK); // проверяем, что ответ 200 OK

        $data = $this->decodeResponse($client); // декодируем json ответ в массив

        self::assertSame($product->getId(), $data['id']); // проверяем, что вернулся именно созданный продукт

        self::assertArrayHasKey('name', $data); // проверяем, что в ответе есть поле name
        self::assertArrayHasKey('description', $data); // description
        self::assertArrayHasKey('price', $data); //  есть поле price
        self::assertArrayHasKey('weight', $data); // есть поле weight
        self::assertArrayHasKey('category', $data); // есть поле category
    }

    public function testShowProductError(): void
    {
        $client = static::createClient(); // создание тестового http-клиента симфони

        $client->request('GET', '/api/products/999999999'); //проверка несуществующего айди

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND); // проверка на статус 404
    }

    public function testCreateProductSuccess(): void
    {
        $client = static::createClient(); // тест-клиент

        $headers = $this->authenticateAsAdmin($client);

        $client->request( //отправляем пост-запрос на ендпоинтс создания продукта
            'POST',
            '/api/products',
            [], // пустые массивы для квери параметров
            [],
            $headers,
            json_encode([
                'name' => 'Created product',
                'description' => 'Created description',
                'price' => 250,
                'weight' => 750,
                'category' => 'created',
            ], JSON_THROW_ON_ERROR) // сборка тела запроса
        );

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        // ^^проверяем что приложение ответило кодом 201

        $data = $this->decodeResponse($client); // декодируем json ответ в массив

        self::assertSame('Created product', $data['name']); // проверка, что вернулось то же имя
        self::assertSame('Created description', $data['description']); // описание
        self::assertSame(250, $data['price']); // цена
        self::assertSame(750, $data['weight']); // масса
        self::assertSame('created', $data['category']); // категория
    }
    public function testCreateProductError(): void
    {
        $client = static::createClient();

        $headers = $this->authenticateAsAdmin($client);

        $client->request(
            'POST',
            '/api/products', // эндпоинт создания продукта
            [], // квери параметры(не нужны)
            [], // файлы не отправляем
            $headers,
            json_encode([
                'name' => '', // невалидное поле
                'description' => 'Created description',
                'price' => 250,
                'weight' => 750,
                'category' => 'created',
            ], JSON_THROW_ON_ERROR) // отправляем невалидные данные (нет name)
        );

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);// 422 ошибка
    }

    public function testUpdateProductSuccess(): void
    {
        $client = static::createClient(); // тестовый клиент

        $headers = $this->authenticateAsAdmin($client);

        $product = $this->createProduct(); // тестовый продукт

        $client->request(
            'PATCH',
            '/api/products/' . $product->getId(), // патч запрос на конкретный продукт
            [],
            [],
            $headers,
            json_encode([
                'name' => 'Updated product',
                'price' => 300,
            ], JSON_THROW_ON_ERROR) // частичное заполнение полей для метода патч
        );

        self::assertResponseStatusCodeSame(Response::HTTP_OK); // 200 обновление прошло успешно

        $data = $this->decodeResponse($client); // декодируем json ответ

        self::assertSame($product->getId(), $data['id']); // проверяем, что вернулся тот же продукт, что обновляли
        self::assertSame('Updated product', $data['name']); // Проверяем, что name изменился
        self::assertSame('Тестовое описание', $data['description']); // description НЕ изменился
        self::assertSame(300, $data['price']); // price изменился
        self::assertSame(500, $data['weight']); // weight НЕ изменился
        self::assertSame('test', $data['category']); // category НЕ изменилась
    }
    public function testUpdateProductError(): void
    {
        $client = static::createClient(); // клиент

        $headers = $this->authenticateAsAdmin($client);

        $product = $this->createProduct(); // продукт. Он должен существовать или проверять будет нечего

        $client->request(
            'PATCH',
            '/api/products/' . $product->getId(), // запрос на существующий продукт
            [],
            [],
            $headers,// тело запроса json
            json_encode([
                'price' => -1, // невалидное значение
            ], JSON_THROW_ON_ERROR)
        );

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY); // 422
        // валидный json, продукт существует, данные невалидные
    }

    public function testDeleteProductSuccess(): void
    {
        $client = static::createClient(); // клиент

        $headers = $this->authenticateAsAdmin($client);

        $product = $this->createProduct(); //создаём продукт, чтобы удалить
        $productId = $product->getId(); // айди в отдельную переменную, чтобы проверить
        // что продукт больше не найти

        $client->request('DELETE', '/api/products/' . $productId, [], [], $headers);
        // делит запрос на конкретный продукт

        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT); // 204 (no content)

        $client->request('GET', '/api/products/' . $productId);
        // пытаемся получить удалённый продукт (проверка)

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND); // 404
        // пытаемся найти удалённый продукт (проверка)
    }
    public function testDeleteProductError(): void
    {
        $client = static::createClient();

        $headers = $this->authenticateAsAdmin($client);

        $client->request('DELETE', '/api/products/999999999', [], [], $headers); // несуществующий айди

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        // проверяем что апи возвращает 404 (not found)
    }

    private function createProduct(): Product
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        //^^ Берём из симфони сервис доктрин ЕМ, чтобы тест мог записать
        //продукт в бд

        $product = new Product(
            'Тестовый продукт',
            'Тестовое описание',
            100,
            500,
            'test'
        );

        $em->persist($product);
        $em->flush();

        return $product;
    }
    private function decodeResponse(KernelBrowser $client): array
    {
        return json_decode(
            $client->getResponse()->getContent(),
            true,
            512,
            \JSON_THROW_ON_ERROR
        );
    }

    private function createUser(string $email, string $plainPassword, array $roles): User
    //создать пользователя в тест бд
    {
        $user = new User($email, '', $roles); // новый пользователь, пас потом

        $passwordHasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        // берём из симфони-контейнера сервис, который хеширует пароли
        $hasherPassword = $passwordHasher->hashPassword($user, $plainPassword);
        //хешируем пароль

        $user->setPassword($hasherPassword); // записываем хешированный пароль юзеру

        $em = static::getContainer()->get(EntityManagerInterface::class);//вызываем ентити_менеджер
        $em->persist($user); // записываем перед сохранением
        $em->flush(); // сохраняем

        return $user; // вернуть user
    }
    private function getToken(KernelBrowser $client, string $email, string $password): string
    // метод, что бы залогинить созданного пользователя
    {
        $client->request( // отправка http запроса
            'POST',
            '/api/login_check',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'email' => $email,
                'password' => $password,
            ], JSON_THROW_ON_ERROR) // сборка тела запроса
        );

        self::assertResponseStatusCodeSame(Response::HTTP_OK); // проверка, -->
        // --> что +логин и вернулся ответ 200 ОК

       $data = $this->decodeResponse($client); // берём json ответ с сервера и делаем из него http
       // массив, который вернёт нам токен

        return $data['token']; // вернуть jwt токен
    }

    private function authenticateAsAdmin(KernelBrowser $client): array
    {
        $email = 'admin_' . uniqid() . '@example.com';
        $password = 'password';

        $this->createUser($email, $password, ['ROLE_ADMIN']);
        $token = $this->getToken($client, $email, $password);

        return [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ];
    }
}
