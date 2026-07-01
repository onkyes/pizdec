<?php

declare(strict_types=1);

namespace App\Tests;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class OrderControllerTest extends WebTestCase
{
    use TestHelper;

    public function testCreateOrderSucceeds(): void
    // проверяет, что пользователь может оформить заказ из корзины
    {
        $client = self::createClient(); // тест клиент

        $headers = $this->authHeaders($client, 'ROLE_USER');
        // создаём юзера и получаем токен
        $product = $this->createProduct(
            category: 'food',
            price: 100,
            weight: 500,
            name: 'Пицца',
        ); // создаём товар, который положим в корзину

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
        ); // добавляем корзину

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        // проверяем, что товар добавился

        $client->request(
            'POST',
            '/api/orders',
            [],
            [],
            $headers,
            json_encode([
                'deliveryRegion' => 'Московская область',
                'deliveryCity' => 'Москва',
                'deliveryStreet' => 'Ленина',
                'deliveryHouse' => '10',
                'deliveryEntrance' => '1',
                'deliveryApartment' => '25',
                'deliveryPostalCode' => '123456',
            ], JSON_THROW_ON_ERROR),
        ); // оформляем заказ с валидным адресом доставки

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED); // проверяем, что заказ создан

        $data = $this->decodeResponse($client); // декодируем json ответ

        self::assertArrayHasKey('id', $data); // проверяем, что у заказа есть id
        self::assertSame('created', $data['status']); // проверяем стартовый статус заказа
        self::assertSame(200, $data['total']); // 100 * 2 = 200

        self::assertSame('Московская область', $data['deliveryAddress']['region']); // проверяем область
        self::assertSame('Москва', $data['deliveryAddress']['city']); // проверяем город
        self::assertSame('Ленина', $data['deliveryAddress']['street']); // проверяем улицу
        self::assertSame('10', $data['deliveryAddress']['house']); // проверяем дом
        self::assertSame('1', $data['deliveryAddress']['entrance']); // проверяем подъезд
        self::assertSame('25', $data['deliveryAddress']['apartment']); // проверяем квартиру
        self::assertSame('123456', $data['deliveryAddress']['postalCode']); // проверяем индекс

        self::assertCount(1, $data['items']); // проверяем, что в заказе один товар

        self::assertSame($product->getId(), $data['items'][0]['productId']); // проверяем id товара
        self::assertSame('Пицца', $data['items'][0]['productName']); // проверяем название товара
        self::assertSame(100, $data['items'][0]['productPrice']); // проверяем цену товара
        self::assertSame(500, $data['items'][0]['productWeight']); // проверяем вес товара
        self::assertSame('food', $data['items'][0]['productCategory']); // проверяем категорию товара
        self::assertSame(2, $data['items'][0]['quantity']); // проверяем количество товара
        self::assertSame(200, $data['items'][0]['lineTotal']); // проверяем сумму строки
    }

    public function testCreateOrderRejectsEmptyBasket(): void
    // проверяет, что нельзя оформить заказ с пустой корзиной
    {
        $client = self::createClient(); // создаём тестовый http-клиент

        $headers = $this->authHeaders($client, 'ROLE_USER'); // создаём юзера и получаем токен

        $client->request(
            'GET',
            '/api/basket',
            [],
            [],
            $headers,
        ); // создаём пустую корзину для пользователя

        self::assertResponseStatusCodeSame(Response::HTTP_OK); // проверяем, что пустая корзина создалась

        $client->request(
            'POST',
            '/api/orders',
            [],
            [],
            $headers,
            json_encode([
                'deliveryRegion' => 'Московская область',
                'deliveryCity' => 'Москва',
                'deliveryStreet' => 'Ленина',
                'deliveryHouse' => '10',
                'deliveryEntrance' => '1',
                'deliveryApartment' => '25',
                'deliveryPostalCode' => '123456',
            ], JSON_THROW_ON_ERROR),
        ); // пробуем оформить заказ без товаров

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY); // ждём 422, потому что корзина пустая
    }

    public function testCreateOrderRejectsInvalidAddress(): void
    // проверяет, что заказ нельзя создать с невалидным адресом доставки
    {
        $client = self::createClient(); // создаём тестовый http-клиент

        $headers = $this->authHeaders($client, 'ROLE_USER'); // создаём юзера и получаем токен

        $product = $this->createProduct(
            category: 'food',
            price: 100,
            weight: 500,
            name: 'Пицца',
        ); // создаём товар, который положим в корзину

        $client->request(
            'POST',
            '/api/basket/items',
            [],
            [],
            $headers,
            json_encode([
                'productId' => $product->getId(),
                'quantity' => 1,
            ], JSON_THROW_ON_ERROR),
        ); // добавляем товар в корзину, чтобы ошибка была именно из-за адреса

        self::assertResponseStatusCodeSame(Response::HTTP_OK); // проверяем, что товар добавился

        $client->request(
            'POST',
            '/api/orders',
            [],
            [],
            $headers,
            json_encode([
                'deliveryRegion' => 'Московская область',
                'deliveryCity' => 'Москва',
                'deliveryStreet' => 'Ленина',
                'deliveryHouse' => '10',
                'deliveryEntrance' => '1',
                'deliveryApartment' => '25',
                'deliveryPostalCode' => 'abc',
            ], JSON_THROW_ON_ERROR),
        ); // пробуем оформить заказ с неправильным индексом

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY); // ждём 422, потому что индекс должен быть из 6 цифр
    }

    public function testListReturnsUserOrders(): void
    // проверяет, что пользователь может получить список своих заказов
    {
        $client = self::createClient(); // создаём тестовый http-клиент

        $headers = $this->authHeaders($client, 'ROLE_USER'); // создаём юзера и получаем токен

        $product = $this->createProduct(
            category: 'food',
            price: 100,
            weight: 500,
            name: 'Пицца',
        ); // создаём товар, который положим в корзину

        $client->request(
            'POST',
            '/api/basket/items',
            [],
            [],
            $headers,
            json_encode([
                'productId' => $product->getId(),
                'quantity' => 1,
            ], JSON_THROW_ON_ERROR),
        ); // добавляем товар в корзину

        self::assertResponseStatusCodeSame(Response::HTTP_OK); // проверяем, что товар добавился

        $client->request(
            'POST',
            '/api/orders',
            [],
            [],
            $headers,
            json_encode([
                'deliveryRegion' => 'Московская область',
                'deliveryCity' => 'Москва',
                'deliveryStreet' => 'Ленина',
                'deliveryHouse' => '10',
                'deliveryEntrance' => '1',
                'deliveryApartment' => '25',
                'deliveryPostalCode' => '123456',
            ], JSON_THROW_ON_ERROR),
        ); // создаём заказ, чтобы потом было что получить списком

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED); // проверяем, что заказ создан

        $createdOrder = $this->decodeResponse($client); // сохраняем созданный заказ

        $client->request(
            'GET',
            '/api/orders',
            [],
            [],
            $headers,
        ); // запрашиваем список заказов текущего пользователя

        self::assertResponseStatusCodeSame(Response::HTTP_OK); // проверяем, что список заказов получен

        $data = $this->decodeResponse($client); // декодируем json ответ в массив

        self::assertCount(1, $data); // проверяем, что в списке один заказ
        self::assertSame($createdOrder['id'], $data[0]['id']); // проверяем, что вернулся созданный заказ
        self::assertSame('created', $data[0]['status']); // проверяем статус заказа
        self::assertSame(100, $data[0]['total']); // проверяем сумму заказа
    }
}
