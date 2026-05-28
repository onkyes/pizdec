<?php

namespace App\Tests;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class ProductControllerTest extends WebTestCase
{
    use TestHelper;
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

        $headers = $this->authHeaders($client, 'ROLE_ADMIN');

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

        $headers = $this->authHeaders($client, 'ROLE_ADMIN');

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

        $headers = $this->authHeaders($client, 'ROLE_ADMIN');

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

        $headers = $this->authHeaders($client, 'ROLE_ADMIN');

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

        $headers = $this->authHeaders($client, 'ROLE_ADMIN');

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

        $headers = $this->authHeaders($client, 'ROLE_ADMIN');

        $client->request('DELETE', '/api/products/999999999', [], [], $headers); // несуществующий айди

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        // проверяем что апи возвращает 404 (not found)
    }

}
