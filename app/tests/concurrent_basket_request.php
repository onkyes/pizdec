<?php

declare(strict_types=1);

use App\Kernel;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

$_SERVER['APP_ENV'] = 'test'; // запускаем именно тестовое окружение
$_ENV['APP_ENV'] = 'test'; // дублируем для dotenv/symfony

$_SERVER['KERNEL_CLASS'] = Kernel::class; // указываем класс ядра symfony
$_ENV['KERNEL_CLASS'] = Kernel::class; // дублируем для окружения

require __DIR__ . '/bootstrap.php'; // подключаем автозагрузку и .env.test

function waitForStartFile(string $startFile): void
    // ждёт файл-сигнал, чтобы два процесса стартовали почти одновременно
{
    $startedAt = microtime(true); // запоминаем время старта ожидания

    while (!is_file($startFile)) {
        // пока файла нет, процесс стоит и ждёт
        if (microtime(true) - $startedAt > 5) {
            throw new RuntimeException('Start file timeout');
        }

        usleep(10_000); // маленькая пауза, чтобы не грузить процессор
    }
}

$operation = $argv[1] ?? null; // add или update
$token = $argv[2] ?? null; // jwt-токен пользователя
$targetId = isset($argv[3]) ? (int) $argv[3] : null; // productId для add или basketItemId для update
$quantity = isset($argv[4]) ? (int) $argv[4] : null; // количество товара
$startFile = $argv[5] ?? null; // файл-сигнал для одновременного старта

try {
    if ($operation === null || $token === null || $targetId === null || $quantity === null || $startFile === null) {
        throw new RuntimeException('Invalid arguments');
    }

    waitForStartFile($startFile); // ждём команду стартовать

    $kernel = new Kernel('test', true); // создаём ядро symfony в тестовом режиме

    $client = new KernelBrowser($kernel); // создаём http-клиент вокруг ядра

    $headers = [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
    ]; // заголовки как в обычном api-запросе

    if ($operation === 'add') {
        $client->request(
            'POST',
            '/api/basket/items',
            [],
            [],
            $headers,
            json_encode([
                'productId' => $targetId,
                'quantity' => $quantity,
            ], JSON_THROW_ON_ERROR),
        ); // добавляем товар в корзину
    } elseif ($operation === 'update') {
        $client->request(
            'PATCH',
            '/api/basket/items/' . $targetId,
            [],
            [],
            $headers,
            json_encode([
                'quantity' => $quantity,
            ], JSON_THROW_ON_ERROR),
        ); // обновляем количество позиции корзины
    } else {
        throw new RuntimeException('Unknown operation');
    }

    $content = (string) $client->getResponse()->getContent(); // берём тело ответа

    try {
        $body = json_decode($content, true, 512, JSON_THROW_ON_ERROR); // пробуем прочитать json
    } catch (Throwable) {
        $body = $content; // если ответ не json, сохраняем как строку
    }

    echo json_encode([
        'statusCode' => $client->getResponse()->getStatusCode(),
        'body' => $body,
    ], JSON_THROW_ON_ERROR); // отдаём результат обратно phpunit-тесту

    exit($client->getResponse()->getStatusCode() >= 500 ? 1 : 0); // 500 считаем падением
} catch (Throwable $e) {
    fwrite(STDERR, $e::class . ': ' . $e->getMessage());

    echo json_encode([
        'statusCode' => 500,
        'error' => $e::class,
        'message' => $e->getMessage(),
    ], JSON_THROW_ON_ERROR);

    exit(1);
}
