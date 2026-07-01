<?php

declare(strict_types=1);

namespace App\Tests;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class BasketControllerTest extends WebTestCase
{
    use TestHelper; // подключаем хелперы для авторизации, создания данных и чтения ответа

    public function testAddCreatesNewItem(): void
    {
        // проверяем, что новый товар добавляется в корзину первой позицией
        $client = self::createClient(); // создаём тестовый клиент, который будет делать запросы к api

        $headers = $this->authHeaders($client, 'ROLE_USER'); // логииним пользователя и получаем заголовки авторизации

        $product = $this->createProduct(
            category: 'food',
            price: 100,
            weight: 500,
            name: 'Пицца',
        ); // создаём тестовый товар, который будем добавлять в корзину

        $client->request(
            'POST', // метод запроса, потому что добавляем новый товар в корзину
            '/api/basket/items', // адрес эндпоинта добавления товара
            [],
            [],
            $headers, // передаём авторизацию, чтобы апи понимало, кто делает запрос
            json_encode([
                'productId' => $product->getId(), // айди товара, который добавляем
                'quantity' => 2, // количество товара
            ], JSON_THROW_ON_ERROR), // превращаем массив в json
        );

        self::assertResponseStatusCodeSame(Response::HTTP_OK); // проверяем, что запрос прошёл успешно

        $data = $this->decodeResponse($client); // достаём json из ответа и превращаем его в массив

        self::assertArrayHasKey('id', $data); // проверяем, что в ответе есть id корзины
        self::assertArrayHasKey('items', $data); // проверяем, что в ответе есть товары корзины
        self::assertArrayHasKey('total', $data); // проверяем, что в ответе есть общая сумма корзины

        self::assertCount(1, $data['items']); // проверяем, что в корзине ровно один товар

        self::assertSame($product->getId(), $data['items'][0]['productId']); // проверяем id товара
        self::assertSame('Пицца', $data['items'][0]['productName']); // проверяем название товара
        self::assertSame(100, $data['items'][0]['price']); // проверяем цену товара
        self::assertSame(2, $data['items'][0]['quantity']); // проверяем количество товара
        self::assertSame(200, $data['items'][0]['lineTotal']); // проверяем сумму по этой позиции
        self::assertSame(200, $data['total']); // проверяем общую сумму корзины
    }
    public function testAddMergesExistingItemQuantity(): void
    {
        // проверяем, что повторное добавление товара увеличивает количество, а не создаёт дубль
        $client = self::createClient(); // создаём тестовый клиент

        $headers = $this->authHeaders($client, 'ROLE_USER'); // создаём юзера и получаем токен

        $product = $this->createProduct(
            category: 'food',
            price: 100,
            weight: 500,
            name: 'Пицца',
        ); // создаём тестовый товар

        $client->request(
            'POST',
            '/api/basket/items', // отправляем запрос на добавление товара в корзину
            [],
            [],
            $headers, // передаём токен авторизации
            json_encode([
                'productId' => $product->getId(), // айди товара, который добавляем
                'quantity' => 2, // добавляем 2 шт
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_OK); // проверяем, что первый запрос прошёл успешно

        $client->request(
            'POST',
            '/api/basket/items', // снова добавляем тот же товар
            [],
            [],
            $headers,
            json_encode([
                'productId' => $product->getId(), // тот же самый товар
                'quantity' => 3, // добавляем ещё 3 штуки
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_OK); // проверяем, что второй запрос тоже прошёл успешно

        $data = $this->decodeResponse($client); // декодируем json ответ в массив

        self::assertCount(1, $data['items']); // проверяем, что товар не задублировался второй строкой
        self::assertSame($product->getId(), $data['items'][0]['productId']); // проверяем, что в корзине нужный товар
        self::assertSame(5, $data['items'][0]['quantity']); // 2 + 3, итоговое количество 5
        self::assertSame(500, $data['items'][0]['lineTotal']); // 100 * 5, сумма по строке 500
        self::assertSame(500, $data['total']); // общая сумма корзины тоже 500
    }

    public function testUpdateChangesQuantity(): void
        // проверяем, что patch меняет количество уже существующей позиции корзины
    {
        $client = self::createClient(); // создаём тестовый http-клиент

        $headers = $this->authHeaders($client, 'ROLE_USER'); // создаём юзера и получаем токен

        $product = $this->createProduct(
            category: 'food',
            price: 100,
            weight: 500,
            name: 'Пицца',
        ); // создаём товар, который будем добавлять в корзину

        $client->request(
            'POST',
            '/api/basket/items',
            [],
            [],
            $headers,
            json_encode([
                'productId' => $product->getId(),
                'quantity' => 2,
            ], JSON_THROW_ON_ERROR),
        ); // сначала добавляем товар в корзину

        self::assertResponseStatusCodeSame(Response::HTTP_OK); // проверяем, что добавление прошло успешно

        $data = $this->decodeResponse($client); // декодируем ответ, чтобы взять id позиции корзины

        $basketItemId = $data['items'][0]['id']; // сохраняем id позиции, потому что patch работает именно с ним

        $client->request(
            'PATCH',
            '/api/basket/items/' . $basketItemId,
            [],
            [],
            $headers,
            json_encode([
                'quantity' => 5,
            ], JSON_THROW_ON_ERROR),
        ); // меняем количество этой позиции с 2 на 5

        self::assertResponseStatusCodeSame(Response::HTTP_OK); // проверяем, что обновление прошло успешно

        $data = $this->decodeResponse($client); // декодируем обновлённую корзину

        self::assertCount(1, $data['items']); // проверяем, что позиция всё ещё одна

        self::assertSame($basketItemId, $data['items'][0]['id']); // проверяем, что обновилась та же позиция
        self::assertSame($product->getId(), $data['items'][0]['productId']); // проверяем, что товар тот же
        self::assertSame(5, $data['items'][0]['quantity']); // проверяем новое количество
        self::assertSame(500, $data['items'][0]['lineTotal']); // 100 * 5 = 500
        self::assertSame(500, $data['total']); // общая сумма корзины тоже 500
    }
    public function testDeleteRemovesItem(): void
        // проверяет, что можно удалить одну конкретную позицию из корзины
    {
        $client = self::createClient(); // создаём тестовый http-клиент

        $headers = $this->authHeaders($client, 'ROLE_USER'); // создаём юзера и получаем токен

        $product = $this->createProduct(
            category: 'food',
            price: 100,
            weight: 500,
            name: 'Пицца',
        ); // создаём товар, который добавим в корзину

        $client->request(
            'POST',
            '/api/basket/items',
            [],
            [],
            $headers,
            json_encode([
                'productId' => $product->getId(),
                'quantity' => 2,
            ], JSON_THROW_ON_ERROR),
        ); // добавляем товар в корзину, чтобы потом было что удалить

        self::assertResponseStatusCodeSame(Response::HTTP_OK); // проверяем, что добавление прошло успешно

        $data = $this->decodeResponse($client); // декодируем ответ корзины

        $basketItemId = $data['items'][0]['id']; // достаём id позиции корзины

        $client->request(
            'DELETE',
            '/api/basket/items/' . $basketItemId,
            [],
            [],
            $headers,
        ); // удаляем конкретную позицию корзины по её id

        self::assertResponseStatusCodeSame(Response::HTTP_OK); // проверяем, что удаление прошло успешно

        $data = $this->decodeResponse($client); // декодируем обновлённую корзину

        self::assertSame([], $data['items']); // проверяем, что товаров в корзине больше нет
        self::assertSame(0, $data['total']); // проверяем, что общая сумма стала 0
    }
    public function testShowCreatesEmptyBasket(): void
        // проверяет, что пользователь может получить свою корзину
        // если корзины ещё нет, она создаётся пустой
    {
        $client = self::createClient(); // создаём тестовый http-клиент

        $headers = $this->authHeaders($client, 'ROLE_USER'); // создаём юзера и получаем токен

        $client->request(
            'GET',
            '/api/basket',
            [],
            [],
            $headers,
        ); // запрашиваем корзину текущего пользователя

        self::assertResponseStatusCodeSame(Response::HTTP_OK); // проверяем, что запрос прошёл успешно

        $data = $this->decodeResponse($client); // декодируем json ответ в массив

        self::assertArrayHasKey('id', $data); // проверяем, что у корзины есть id
        self::assertSame([], $data['items']); // проверяем, что новая корзина пустая
        self::assertSame(0, $data['total']); // проверяем, что сумма пустой корзины равна 0
    }
    public function testClearEmptiesBasket(): void
        // проверяет, что можно очистить всю корзину сразу
    {
        $client = self::createClient(); // создаём тестовый http-клиент

        $headers = $this->authHeaders($client, 'ROLE_USER'); // создаём юзера и получаем токен

        $food = $this->createProduct(
            category: 'food',
            price: 100,
            weight: 500,
            name: 'Пицца',
        ); // создаём первый товар

        $drink = $this->createProduct(
            category: 'drink',
            price: 50,
            weight: 300,
            name: 'Кола',
        ); // создаём второй товар

        $client->request(
            'POST',
            '/api/basket/items',
            [],
            [],
            $headers,
            json_encode([
                'productId' => $food->getId(),
                'quantity' => 2,
            ], JSON_THROW_ON_ERROR),
        ); // добавляем первый товар в корзину

        self::assertResponseStatusCodeSame(Response::HTTP_OK); // проверяем, что первый товар добавился

        $client->request(
            'POST',
            '/api/basket/items',
            [],
            [],
            $headers,
            json_encode([
                'productId' => $drink->getId(),
                'quantity' => 3,
            ], JSON_THROW_ON_ERROR),
        ); // добавляем второй товар в корзину

        self::assertResponseStatusCodeSame(Response::HTTP_OK); // проверяем, что второй товар добавился

        $client->request(
            'DELETE',
            '/api/basket/items',
            [],
            [],
            $headers,
        ); // очищаем всю корзину целиком

        self::assertResponseStatusCodeSame(Response::HTTP_OK); // проверяем, что очистка прошла успешно

        $data = $this->decodeResponse($client); // декодируем обновлённую корзину

        self::assertSame([], $data['items']); // проверяем, что в корзине больше нет товаров
        self::assertSame(0, $data['total']); // проверяем, что сумма корзины стала 0
    }

}
