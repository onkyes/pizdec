<?php

declare(strict_types=1);

namespace App\Tests;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class BasketLimitTest extends WebTestCase
{
    use TestHelper;

    public function testFoodLimitClampsToTen(): void
    // проверяет, что в корзину нельзя добавить больше 10 товаров категории food
    {
        $client = self::createClient(); // создаём тестовый хттп-клиент

        $headers = $this->authHeaders($client, 'ROLE_USER'); // создаём юзера и получаем токен

        $product = $this->createProduct(
            category: 'food',
            price: 100,
            weight: 500,
            name: 'Пицца',
        ); // создаём товар категории food

        $client->request(
            'POST',
            '/api/basket/items',
            [],
            [],
            $headers,
            json_encode([
                'productId' => $product->getId(),
                'quantity' => 11,
            ], JSON_THROW_ON_ERROR),
        ); // пробуем добавить 11 food, хотя лимит 10

        self::assertResponseStatusCodeSame(Response::HTTP_OK); // запрос проходит, но количество будет обрезано до лимита

        $data = $this->decodeResponse($client); // декодируем json ответ в массив

        self::assertCount(1, $data['items']); // проверяем, что в корзине одна позиция
        self::assertSame(10, $data['items'][0]['quantity']); // проверяем, что quantity стало 10, а не 11
        self::assertSame(1_000, $data['items'][0]['lineTotal']); // 100 * 10 = 1000
        self::assertSame(1_000, $data['total']); // вся корзина тоже на 1000
    }

    public function testDrinkLimitClampsToTwenty(): void
    // проверяет, что в корзину нельзя добавить больше 20 товаров категории drink
    {
        $client = self::createClient(); // создаём тестовый http-клиент

        $headers = $this->authHeaders($client, 'ROLE_USER'); // создаём юзера и получаем токен

        $product = $this->createProduct(
            category: 'drink',
            price: 50,
            weight: 300,
            name: 'Кола',
        ); // создаём товар категории drink

        $client->request(
            'POST',
            '/api/basket/items',
            [],
            [],
            $headers,
            json_encode([
                'productId' => $product->getId(),
                'quantity' => 21,
            ], JSON_THROW_ON_ERROR),
        ); // пробуем добавить 21 drink, хотя лимит 20

        self::assertResponseStatusCodeSame(Response::HTTP_OK); // запрос проходит, но количество будет обрезано до лимита

        $data = $this->decodeResponse($client); // декодируем json ответ в массив

        self::assertCount(1, $data['items']); // проверяем, что в корзине одна позиция
        self::assertSame(20, $data['items'][0]['quantity']); // проверяем, что quantity стало 20, а не 21
        self::assertSame(1_000, $data['items'][0]['lineTotal']); // 50 * 20 = 1000
        self::assertSame(1_000, $data['total']); // вся корзина тоже на 1000
    }

    public function testFoodLimitCountsExistingQuantity(): void
    // проверяет, что лимит food считается с учётом товаров, которые уже лежат в корзине
    {
        $client = self::createClient(); // создаём тестовый http-клиент

        $headers = $this->authHeaders($client, 'ROLE_USER'); // создаём юзера и получаем токен

        $product = $this->createProduct(
            category: 'food',
            price: 100,
            weight: 500,
            name: 'Пицца',
        ); // создаём товар категории food

        $client->request(
            'POST',
            '/api/basket/items',
            [],
            [],
            $headers,
            json_encode([
                'productId' => $product->getId(),
                'quantity' => 8,
            ], JSON_THROW_ON_ERROR),
        ); // сначала кладём в корзину 8 food

        self::assertResponseStatusCodeSame(Response::HTTP_OK); // проверяем, что первый запрос прошёл успешно

        $client->request(
            'POST',
            '/api/basket/items',
            [],
            [],
            $headers,
            json_encode([
                'productId' => $product->getId(),
                'quantity' => 5,
            ], JSON_THROW_ON_ERROR),
        ); // потом пробуем добавить ещё 5 food

        self::assertResponseStatusCodeSame(Response::HTTP_OK); // запрос проходит, но добавится только 2 до лимита

        $data = $this->decodeResponse($client); // декодируем json ответ в массив

        self::assertCount(1, $data['items']); // проверяем, что позиция с этим товаром одна
        self::assertSame(10, $data['items'][0]['quantity']); // проверяем, что получилось 10, а не 13
        self::assertSame(1_000, $data['items'][0]['lineTotal']); // 100 * 10 = 1000
        self::assertSame(1_000, $data['total']); // общая сумма корзины тоже 1000
    }

    public function testFoodAndDrinkLimitsAreIndependent(): void
    // проверяет, что food и drink считаются отдельно и не мешают друг другу
    {
        $client = self::createClient(); // создаём тестовый http-клиент

        $headers = $this->authHeaders($client, 'ROLE_USER'); // создаём юзера и получаем токен

        $food = $this->createProduct(
            category: 'food',
            price: 100,
            weight: 500,
            name: 'Пицца',
        ); // создаём товар категории food

        $drink = $this->createProduct(
            category: 'drink',
            price: 50,
            weight: 300,
            name: 'Кола',
        ); // создаём товар категории drink

        $client->request(
            'POST',
            '/api/basket/items',
            [],
            [],
            $headers,
            json_encode([
                'productId' => $food->getId(),
                'quantity' => 10,
            ], JSON_THROW_ON_ERROR),
        ); // добавляем 10 food, это максимум для еды

        self::assertResponseStatusCodeSame(Response::HTTP_OK); // проверяем, что food добавился

        $client->request(
            'POST',
            '/api/basket/items',
            [],
            [],
            $headers,
            json_encode([
                'productId' => $drink->getId(),
                'quantity' => 20,
            ], JSON_THROW_ON_ERROR),
        ); // добавляем 20 drink, это максимум для напитков

        self::assertResponseStatusCodeSame(Response::HTTP_OK); // проверяем, что drink добавился

        $data = $this->decodeResponse($client); // декодируем json ответ в массив

        self::assertCount(2, $data['items']); // проверяем, что в корзине две позиции

        $quantitiesByProductId = []; // сюда сложим quantity по айди товара

        foreach ($data['items'] as $item) {
            $quantitiesByProductId[$item['productId']] = $item['quantity']; // запоминаем количество каждого товара
        }

        self::assertSame(10, $quantitiesByProductId[$food->getId()]); // проверяем, что food осталось 10
        self::assertSame(20, $quantitiesByProductId[$drink->getId()]); // проверяем, что drink стало 20

        self::assertSame(2_000, $data['total']); // 10 * 100 + 20 * 50 = 2000
    }

    public function testUpdateRejectsQuantityOverCategoryLimit(): void
    // проверяет, что через patch нельзя поставить quantity выше лимита категории
    {
        $client = self::createClient(); // создаём тестовый http-клиент

        $headers = $this->authHeaders($client, 'ROLE_USER'); // создаём юзера и получаем токен

        $product = $this->createProduct(
            category: 'food',
            price: 100,
            weight: 500,
            name: 'Пицца',
        ); // создаём товар категории food

        $client->request(
            'POST',
            '/api/basket/items',
            [],
            [],
            $headers,
            json_encode([
                'productId' => $product->getId(),
                'quantity' => 10,
            ], JSON_THROW_ON_ERROR),
        ); // сначала кладём в корзину 10 food, это максимум

        self::assertResponseStatusCodeSame(Response::HTTP_OK); // проверяем, что товар добавился

        $data = $this->decodeResponse($client); // декодируем ответ корзины

        $basketItemId = $data['items'][0]['id']; // достаём id позиции корзины

        $client->request(
            'PATCH',
            '/api/basket/items/' . $basketItemId,
            [],
            [],
            $headers,
            json_encode([
                'quantity' => 11,
            ], JSON_THROW_ON_ERROR),
        ); // пробуем руками поставить quantity выше лимита food

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY); // ждём 422, потому что лимит нарушен
    }
}
