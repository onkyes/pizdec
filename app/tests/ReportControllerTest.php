<?php

declare(strict_types=1);

namespace App\Tests;

use App\Entity\OutboxMessage;
use App\Entity\Report;
use App\Message\GenerateReportMessage;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemOperator;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class ReportControllerTest extends WebTestCase
{
    use TestHelper;

    public function testCreateReportCreatesOutboxMessage(): void
    // проверяет, что админ может создать отчёт, а генерация уходит в outbox
    {
        $client = self::createClient(); // создаём тестовый http-клиент
        $client->disableReboot(); // оставляем один kernel для доступа к контейнеру после запроса

        $headers = $this->authHeaders($client, 'ROLE_ADMIN'); // логинимся админом

        $client->request(
            'POST',
            '/api/reports',
            [],
            [],
            $headers,
            json_encode([
                'periodFrom' => '2026-08-01 00:00:00',
                'periodTo' => '2026-08-02 00:00:00',
            ], JSON_THROW_ON_ERROR),
        ); // создаём отчёт за период

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        // проверяем, что отчёт создан

        $data = $this->decodeResponse($client);
        // декодируем json ответа

        self::assertArrayHasKey('id', $data);
        // проверяем, что у отчёта появился id

        self::assertSame('pending', $data['status']);
        // после создания отчёт ещё ждёт обработки

        self::assertNull($data['filePath']);
        // файл ещё не создан

        $em = self::getContainer()->get(EntityManagerInterface::class);
        // достаём entity manager, чтобы проверить outbox-запись

        $outboxMessages = $em->getRepository(OutboxMessage::class)->findBy([
            'messageClass' => GenerateReportMessage::class,
        ]);
        // ищем outbox-сообщения для генерации отчётов

        $matchedOutboxMessages = array_values(array_filter(
            $outboxMessages,
            static fn(OutboxMessage $outboxMessage): bool => $outboxMessage->getPayload() === ['reportId' => $data['id']],
        ));
        // оставляем только outbox-запись для созданного отчёта

        self::assertCount(1, $matchedOutboxMessages);
        // для созданного отчёта должна быть одна outbox-запись

        $outboxMessage = $matchedOutboxMessages[0];
        // достаём созданную outbox-запись

        self::assertInstanceOf(OutboxMessage::class, $outboxMessage);
        // проверяем тип записи

        self::assertSame(['reportId' => $data['id']], $outboxMessage->getPayload());
        // проверяем, что в payload лежит id созданного отчёта

        self::assertNull($outboxMessage->getPublishedAt());
        // сообщение ещё не отправлено в RabbitMQ
    }

    public function testShowReportReturnsReportStatus(): void
    // проверяет, что админ может получить отчёт по id
    {
        $client = self::createClient(); // создаём тестовый http-клиент

        $headers = $this->authHeaders($client, 'ROLE_ADMIN'); // логинимся админом

        $em = self::getContainer()->get(EntityManagerInterface::class);
        // достаём entity manager, чтобы подготовить отчёт в базе

        $report = new Report(
            new \DateTimeImmutable('2026-08-01 00:00:00'),
            new \DateTimeImmutable('2026-08-02 00:00:00'),
        ); // создаём отчёт в статусе pending

        $em->persist($report); // готовим отчёт к сохранению
        $em->flush(); // сохраняем отчёт, чтобы появился id

        $client->request(
            'GET',
            '/api/reports/' . $report->getId(),
            [],
            [],
            $headers,
        ); // запрашиваем отчёт по id

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        // проверяем, что отчёт найден

        $data = $this->decodeResponse($client);
        // декодируем json ответа

        self::assertSame($report->getId(), $data['id']);
        // проверяем id отчёта

        self::assertSame('pending', $data['status']);
        // проверяем статус отчёта

        self::assertNull($data['filePath']);
        // файл ещё не создан
    }

    public function testDownloadCompletedReportReturnsJsonlFile(): void
    // проверяет, что готовый отчёт можно скачать
    {
        $client = self::createClient(); // создаём тестовый http-клиент

        $headers = $this->authHeaders($client, 'ROLE_ADMIN'); // логинимся админом

        $em = self::getContainer()->get(EntityManagerInterface::class);
        // достаём entity manager для подготовки отчёта

        $reportsStorage = self::getContainer()->get('reports.storage');
        // достаём test storage отчётов

        self::assertInstanceOf(FilesystemOperator::class, $reportsStorage);
        // проверяем, что это Flysystem storage

        $report = new Report(
            new \DateTimeImmutable('2026-08-01 00:00:00'),
            new \DateTimeImmutable('2026-08-02 00:00:00'),
        ); // создаём отчёт

        $filePath = 'test-report-' . uniqid() . '.jsonl';
        // уникальное имя файла, чтобы тесты не конфликтовали

        $report->markCompleted($filePath);
        // переводим отчёт в completed и сохраняем путь к файлу

        $reportsStorage->write(
            $filePath,
            "{\"product_name\":\"Пицца\",\"price\":100,\"user\":{\"id\":1}}\n"
            . "{\"product_name\":\"Пицца\",\"price\":100,\"user\":{\"id\":1}}\n",
        ); // кладём тестовый jsonl-файл в storage

        $em->persist($report); // готовим отчёт к сохранению
        $em->flush(); // сохраняем отчёт

        $client->request(
            'GET',
            '/api/reports/' . $report->getId() . '/download',
            [],
            [],
            $headers,
        ); // скачиваем файл отчёта

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        // проверяем успешный ответ

        self::assertSame(
            'application/x-ndjson',
            $client->getResponse()->headers->get('Content-Type'),
        ); // проверяем content type jsonl

        self::assertStringContainsString(
            'attachment; filename="' . basename($filePath) . '"',
            (string) $client->getResponse()->headers->get('Content-Disposition'),
        ); // проверяем, что файл отдаётся как attachment

        self::assertSame(
            "{\"product_name\":\"Пицца\",\"price\":100,\"user\":{\"id\":1}}\n"
            . "{\"product_name\":\"Пицца\",\"price\":100,\"user\":{\"id\":1}}\n",
            $client->getInternalResponse()->getContent(),
        ); // проверяем содержимое скачанного файла
    }

    public function testDownloadPendingReportReturnsConflict(): void
    // проверяет, что нельзя скачать отчёт, который ещё не готов
    {
        $client = self::createClient(); // создаём тестовый http-клиент

        $headers = $this->authHeaders($client, 'ROLE_ADMIN'); // логинимся админом

        $em = self::getContainer()->get(EntityManagerInterface::class);
        // достаём entity manager для подготовки отчёта

        $report = new Report(
            new \DateTimeImmutable('2026-08-01 00:00:00'),
            new \DateTimeImmutable('2026-08-02 00:00:00'),
        ); // создаём pending-отчёт без файла

        $em->persist($report); // готовим отчёт к сохранению
        $em->flush(); // сохраняем отчёт, чтобы появился id

        $client->request(
            'GET',
            '/api/reports/' . $report->getId() . '/download',
            [],
            [],
            $headers,
        ); // пробуем скачать ещё не готовый отчёт

        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        // ждём 409, потому что отчёт ещё не completed

        $data = $this->decodeResponse($client);
        // декодируем json ошибки

        self::assertSame('Отчёт ещё не готов', $data['message']);
        // проверяем текст ошибки
    }

    public function testShowMissingReportReturnsNotFound(): void
    // проверяет, что для несуществующего отчёта возвращается 404
    {
        $client = self::createClient(); // создаём тестовый http-клиент

        $headers = $this->authHeaders($client, 'ROLE_ADMIN'); // логинимся админом

        $client->request(
            'GET',
            '/api/reports/999999999',
            [],
            [],
            $headers,
        ); // запрашиваем отчёт, которого нет

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        // ждём 404

        $data = $this->decodeResponse($client);
        // декодируем json ошибки

        self::assertSame('Отчёт не найден', $data['message']);
        // проверяем текст ошибки
    }

    public function testDownloadMissingReportReturnsNotFound(): void
    // проверяет, что скачать несуществующий отчёт нельзя
    {
        $client = self::createClient(); // создаём тестовый http-клиент

        $headers = $this->authHeaders($client, 'ROLE_ADMIN'); // логинимся админом

        $client->request(
            'GET',
            '/api/reports/999999999/download',
            [],
            [],
            $headers,
        ); // запрашиваем скачивание отчёта, которого нет

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        // ждём 404

        $data = $this->decodeResponse($client);
        // декодируем json ошибки

        self::assertSame('Отчёт не найден', $data['message']);
        // проверяем текст ошибки
    }

    public function testCreateReportRequiresAdminRole(): void
    // проверяет, что обычный пользователь не может создать отчёт
    {
        $client = self::createClient(); // создаём тестовый http-клиент

        $headers = $this->authHeaders($client, 'ROLE_USER'); // логинимся обычным пользователем

        $client->request(
            'POST',
            '/api/reports',
            [],
            [],
            $headers,
            json_encode([
                'periodFrom' => '2026-08-01 00:00:00',
                'periodTo' => '2026-08-02 00:00:00',
            ], JSON_THROW_ON_ERROR),
        ); // пробуем создать отчёт без роли админа

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        // ждём 403, потому что контроллер закрыт ROLE_ADMIN
    }

    public function testCreateReportRejectsInvalidPeriod(): void
    // проверяет, что нельзя создать отчёт с началом периода позже конца
    {
        $client = self::createClient(); // создаём тестовый http-клиент

        $headers = $this->authHeaders($client, 'ROLE_ADMIN'); // логинимся админом

        $client->request(
            'POST',
            '/api/reports',
            [],
            [],
            $headers,
            json_encode([
                'periodFrom' => '2026-08-02 00:00:00',
                'periodTo' => '2026-08-01 00:00:00',
            ], JSON_THROW_ON_ERROR),
        ); // пробуем создать отчёт с неправильным периодом

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        // ждём 400 из-за невалидного периода

        $data = $this->decodeResponse($client);
        // декодируем json ошибки

        self::assertSame('Начало периода отчёта не может быть позже окончания.', $data['message']);
        // проверяем текст ошибки
    }
}
