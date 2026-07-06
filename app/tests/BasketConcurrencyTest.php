<?php

declare(strict_types=1);

namespace App\Tests;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Process\Process;

final class BasketConcurrencyTest extends WebTestCase
{
    use TestHelper;

    /**
     * проверяет, что параллельное обновление одной позиции не ломает корзину.
     */
    public function testConcurrentUpdateSameItemIsPredictable(): void
    {
        $client = self::createClient(); // создаём тестовый http-клиент

        $headers = $this->authHeaders($client, 'ROLE_USER'); // создаём юзера и получаем токен

        $token = substr($headers['HTTP_AUTHORIZATION'], \strlen('Bearer '));
        // ^^достаём сам токен без слова bearer

        $product = $this->createProduct(
            category: 'food',
            price: 100,
            weight: 500,
            name: 'Пицца',
        ); // ^^создаём товар категории food

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

        self::assertResponseStatusCodeSame(Response::HTTP_OK); // проверяем, что товар добавился

        $data = $this->decodeResponse($client); // декодируем ответ корзины

        $basketItemId = $data['items'][0]['id']; // достаём id позиции корзины

        $startFile = sys_get_temp_dir()
            . \DIRECTORY_SEPARATOR
            . 'basket_concurrency_'
            . uniqid(
                '',
                true,
            )
            . '.start';
        // файл-сигнал, по которому два процесса стартуют одновременно

        $script = __DIR__ . '/concurrent_basket_request.php'; // путь до helper-скрипта

        $firstProcess = new Process([
            PHP_BINARY,
            $script,
            'update',
            $token,
            (string) $basketItemId,
            '4',
            $startFile,
        ], \dirname(__DIR__)); // первый процесс попробует поставить quantity = 4

        $secondProcess = new Process([
            PHP_BINARY,
            $script,
            'update',
            $token,
            (string) $basketItemId,
            '7',
            $startFile,
        ], \dirname(__DIR__)); // второй процесс попробует поставить quantity = 7

        $firstProcess->setTimeout(10); // ограничиваем время, чтобы тест не завис навсегда
        $secondProcess->setTimeout(10); // то же самое для второго процесса

        $firstProcess->start(); // запускаем первый процесс
        $secondProcess->start(); // запускаем второй процесс

        usleep(100_000); // даём процессам дойти до ожидания start-файла

        file_put_contents($startFile, 'start'); // даём обоим процессам сигнал стартовать

        $firstProcess->wait(); // ждём завершения первого процесса
        $secondProcess->wait(); // ждём завершения второго процесса

        if (is_file($startFile)) {
            unlink($startFile); // удаляем временный файл-сигнал
        }

        $firstResult = $this->decodeProcessOutput($firstProcess); // читаем результат первого процесса
        $secondResult = $this->decodeProcessOutput($secondProcess); // читаем результат второго процесса

        $statusCodes = [
            $firstResult['statusCode'],
            $secondResult['statusCode'],
        ]; // собираем статусы двух параллельных запросов

        foreach ($statusCodes as $statusCode) {
            self::assertContains($statusCode, [
                Response::HTTP_OK,
                Response::HTTP_CONFLICT,
            ]); // каждый запрос должен закончиться успехом или понятным conflict, но не 500
        }

        self::assertContains(Response::HTTP_OK, $statusCodes); // хотя бы один запрос должен успешно изменить корзину

        self::ensureKernelShutdown(); // перезапускаем kernel, чтобы не читать старое состояние entity manager

        $client = self::createClient(); // создаём свежий клиент после параллельных процессов

        $client->request(
            'GET',
            '/api/basket',
            [],
            [],
            $headers,
        ); // заново читаем корзину из базы

        self::assertResponseStatusCodeSame(Response::HTTP_OK); // проверяем, что корзина читается

        $data = $this->decodeResponse($client); // декодируем свежий ответ корзины

        self::assertCount(1, $data['items']); // проверяем, что позиция не задублировалась
        self::assertContains($data['items'][0]['quantity'], [4, 7]); // итог должен быть одним из двух обновлений
    }

    /**
     * проверяет, что два параллельных добавления не могут пробить лимит food.
     */
    public function testConcurrentAddOnFoodLimitBoundaryDoesNotExceedLimit(): void
    {
        $client = self::createClient(); // создаём тестовый http-клиент

        $headers = $this->authHeaders($client, 'ROLE_USER'); // создаём юзера и получаем токен

        $token = substr($headers['HTTP_AUTHORIZATION'], \strlen('Bearer ')); // достаём сам токен без слова bearer

        $initialProduct = $this->createProduct(
            category: 'food',
            price: 100,
            weight: 500,
            name: 'Пицца 1',
        ); // создаём товар, которым заранее заполним корзину до 8

        $firstProduct = $this->createProduct(
            category: 'food',
            price: 100,
            weight: 500,
            name: 'Пицца 2',
        ); // первый товар для параллельного добавления

        $secondProduct = $this->createProduct(
            category: 'food',
            price: 100,
            weight: 500,
            name: 'Пицца 3',
        ); // второй товар для параллельного добавления

        $client->request(
            'POST',
            '/api/basket/items',
            [],
            [],
            $headers,
            json_encode([
                'productId' => $initialProduct->getId(),
                'quantity' => 8,
            ], JSON_THROW_ON_ERROR),
        ); // заранее кладём 8 food в корзину

        self::assertResponseStatusCodeSame(Response::HTTP_OK); // проверяем, что первые 8 товаров добавились

        $startFile = sys_get_temp_dir() . \DIRECTORY_SEPARATOR . 'basket_concurrency_' . uniqid('', true) . '.start';
        // файл-сигнал, по которому два процесса стартуют одновременно

        $script = __DIR__ . '/concurrent_basket_request.php'; // путь до helper-скрипта

        $firstProcess = new Process([
            PHP_BINARY,
            $script,
            'add',
            $token,
            (string) $firstProduct->getId(),
            '2',
            $startFile,
        ], \dirname(__DIR__)); // первый процесс попробует добавить ещё 2 food

        $secondProcess = new Process([
            PHP_BINARY,
            $script,
            'add',
            $token,
            (string) $secondProduct->getId(),
            '2',
            $startFile,
        ], \dirname(__DIR__)); // второй процесс тоже попробует добавить ещё 2 food

        $firstProcess->setTimeout(10); // ограничиваем время, чтобы тест не завис навсегда
        $secondProcess->setTimeout(10); // то же самое для второго процесса

        $firstProcess->start(); // запускаем первый процесс
        $secondProcess->start(); // запускаем второй процесс

        usleep(100_000); // даём процессам дойти до ожидания start-файла

        file_put_contents($startFile, 'start'); // даём обоим процессам сигнал стартовать

        $firstProcess->wait(); // ждём завершения первого процесса
        $secondProcess->wait(); // ждём завершения второго процесса

        if (is_file($startFile)) {
            unlink($startFile); // удаляем временный файл-сигнал
        }

        $firstResult = $this->decodeProcessOutput($firstProcess); // читаем результат первого процесса
        $secondResult = $this->decodeProcessOutput($secondProcess); // читаем результат второго процесса

        $statusCodes = [
            $firstResult['statusCode'],
            $secondResult['statusCode'],
        ]; // собираем статусы двух параллельных запросов

        foreach ($statusCodes as $statusCode) {
            self::assertContains($statusCode, [
                Response::HTTP_OK,
                Response::HTTP_CONFLICT,
            ]); // каждый запрос должен закончиться успехом или понятным conflict, но не 500
        }

        self::assertContains(Response::HTTP_OK, $statusCodes); // хотя бы один запрос должен успешно выполниться

        self::ensureKernelShutdown(); // перезапускаем kernel, чтобы не читать старое состояние entity manager

        $client = self::createClient(); // создаём свежий клиент после параллельных процессов

        $client->request(
            'GET',
            '/api/basket',
            [],
            [],
            $headers,
        ); // заново читаем корзину из базы

        self::assertResponseStatusCodeSame(Response::HTTP_OK); // проверяем, что корзина читается

        $data = $this->decodeResponse($client); // декодируем свежий ответ корзины

        $totalQuantity = 0; // сюда сложим всё количество товаров в корзине

        foreach ($data['items'] as $item) {
            $totalQuantity += $item['quantity']; // прибавляем quantity каждой позиции
        }

        self::assertSame(10, $totalQuantity); // проверяем, что параллельные запросы не пробили лимит food = 10
    }

    /**
     * превращает json из дочернего процесса в обычный php-массив.
     *
     * @return array<string, mixed>
     */
    private function decodeProcessOutput(Process $process): array
    {
        $output = trim($process->getOutput()); // берём stdout процесса

        self::assertNotSame('', $output, $process->getErrorOutput()); // проверяем, что процесс что-то вернул

        $data = json_decode($output, true, 512, JSON_THROW_ON_ERROR); // декодируем json

        self::assertIsArray($data); // проверяем, что получился массив
        self::assertArrayHasKey('statusCode', $data); // проверяем, что в ответе есть http-статус

        return $data;
    }
}
