<?php

declare(strict_types=1);

namespace App\Tests;

use App\Entity\BuyerOrder;
use App\Entity\BuyerOrderItem;
use App\Entity\Report;
use App\Entity\User;
use App\Enum\DeliveryType;
use App\Enum\OrderStatus;
use App\Enum\ReportStatus;
use App\Message\GenerateReportMessage;
use App\Message\ReportCompletedMessage;
use App\MessageHandler\GenerateReportMessageHandler;
use App\Repository\BuyerOrderItemRepository;
use App\Repository\ReportRepository;
use App\Service\ReportGeneratorService;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\UnableToWriteFile;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

final class GenerateReportMessageHandlerTest extends KernelTestCase
{
    public function testGenerateReportCreatesJsonlFileAndMarksReportCompleted(): void
    // проверяет успешную генерацию отчёта через handler
    {
        self::bootKernel(); // запускаем kernel без http-клиента

        $em = self::getContainer()->get(EntityManagerInterface::class);
        // достаём entity manager для подготовки данных

        $reportsStorage = self::getContainer()->get('reports.storage');
        // достаём test storage отчётов

        self::assertInstanceOf(FilesystemOperator::class, $reportsStorage);
        // проверяем, что это Flysystem storage

        $completedTransport = self::getContainer()->get('messenger.transport.reports_completed');
        // достаём test transport для completed-событий

        self::assertInstanceOf(InMemoryTransport::class, $completedTransport);
        // проверяем, что completed transport в тестах in-memory

        $completedTransport->reset();
        // очищаем transport перед тестом
        $year = random_int(2_100, 9_999);
        $periodFrom = new \DateTimeImmutable($year . '-08-03 00:00:00');
        $periodTo = new \DateTimeImmutable($year . '-08-04 00:00:00');
        $orderCreatedAt = new \DateTimeImmutable($year . '-08-03 12:00:00');

        $user = new User(
            'report_user_' . uniqid() . '@example.com',
            'password',
            ['ROLE_USER'],
        ); // создаём пользователя заказа

        $order = new BuyerOrder(
            $user,
            OrderStatus::Completed,
            200,
            DeliveryType::Pickup,
            null,
            null,
            null,
            null,
            null,
        ); // создаём завершённый заказ

        $order->setCreatedAt($orderCreatedAt);
        // ставим дату заказа внутрь периода отчёта

        $orderItem = new BuyerOrderItem(
            $order,
            10,
            'Пицца',
            100,
            500,
            'food',
            2,
            200,
        ); // создаём проданный товар

        $order->addItem($orderItem);
        // связываем строку заказа с заказом

        $report = new Report($periodFrom, $periodTo);

        $em->persist($user);
        $em->persist($order);
        $em->persist($orderItem);
        $em->persist($report);
        $em->flush();
        // сохраняем все данные в тестовую БД

        $handler = self::getContainer()->get(GenerateReportMessageHandler::class);
        // достаём реальный handler из контейнера

        $handler(new GenerateReportMessage($report->getId()));
        // запускаем генерацию отчёта как будто сообщение пришло из очереди

        $em->refresh($report);
        // обновляем entity из БД после работы handler

        self::assertSame(ReportStatus::Completed, $report->getStatus());
        // отчёт должен стать completed

        self::assertSame('report-' . $report->getId() . '.jsonl', $report->getFilePath());
        // проверяем путь к файлу

        self::assertNull($report->getErrorMessage());
        // ошибки быть не должно

        self::assertNotNull($report->getCompletedAt());
        // дата завершения должна быть заполнена

        $content = $reportsStorage->read($report->getFilePath());
        // читаем созданный jsonl-файл из test storage

        self::assertSame(
            "{\"product_name\":\"Пицца\",\"price\":100,\"user\":{\"id\":{$user->getId()}}}\n"
            . "{\"product_name\":\"Пицца\",\"price\":100,\"user\":{\"id\":{$user->getId()}}}\n",
            $content,
        ); // проверяем содержимое jsonl

        $sentMessages = $completedTransport->getSent();
        // смотрим completed-события

        self::assertCount(1, $sentMessages);
        // после генерации должно уйти одно completed-сообщение

        $message = $sentMessages[0]->getMessage();
        // достаём сообщение из envelope

        self::assertInstanceOf(ReportCompletedMessage::class, $message);
        // проверяем тип completed-события

        self::assertSame($report->getId(), $message->reportId);
        // проверяем id отчёта в completed-событии
    }

    public function testGenerateReportMarksReportFailedWhenStorageWriteFails(): void
    // проверяет, что отчёт становится failed, если storage недоступен
    {
        self::bootKernel(); // запускаем kernel без http-клиента

        $em = self::getContainer()->get(EntityManagerInterface::class);
        // достаём entity manager для подготовки данных

        $user = new User(
            'failed_report_user_' . uniqid() . '@example.com',
            'password',
            ['ROLE_USER'],
        ); // создаём пользователя заказа

        $order = new BuyerOrder(
            $user,
            OrderStatus::Completed,
            100,
            DeliveryType::Pickup,
            null,
            null,
            null,
            null,
            null,
        ); // создаём заказ

        $order->setCreatedAt(new \DateTimeImmutable('2036-08-05 12:00:00'));
        // ставим дату заказа внутрь периода отчёта

        $orderItem = new BuyerOrderItem(
            $order,
            10,
            'Пицца',
            100,
            500,
            'food',
            1,
            100,
        ); // создаём строку заказа

        $order->addItem($orderItem);
        // связываем строку заказа с заказом

        $report = new Report(
            new \DateTimeImmutable('2036-08-05 00:00:00'),
            new \DateTimeImmutable('2036-08-06 00:00:00'),
        ); // создаём pending отчёт

        $em->persist($user);
        $em->persist($order);
        $em->persist($orderItem);
        $em->persist($report);
        $em->flush();
        // сохраняем данные

        $failingStorage = $this->createMock(FilesystemOperator::class);
        // создаём mock storage, который имитирует падение MinIO/диска

        $failingStorage
            ->expects(self::once())
            ->method('writeStream')
            ->willThrowException(UnableToWriteFile::atLocation('report.jsonl', 'storage unavailable'));
        // при записи файла кидаем ошибку storage

        $generator = new ReportGeneratorService(
            self::getContainer()->get(ReportRepository::class),
            self::getContainer()->get(BuyerOrderItemRepository::class),
            $failingStorage,
            $em,
            self::getContainer()->get(MessageBusInterface::class),
        ); // вручную собираем сервис с падающим storage

        try {
            $generator->generate($report->getId());
            self::fail('Expected storage exception.');
        } catch (UnableToWriteFile) {
            // ожидаем ошибку записи
        }

        $em->refresh($report);
        // обновляем отчёт из БД

        self::assertSame(ReportStatus::Failed, $report->getStatus());
        // отчёт должен перейти в failed

        self::assertStringContainsString('storage unavailable', (string) $report->getErrorMessage());
        // причина ошибки должна сохраниться

        self::assertNotNull($report->getCompletedAt());
        // completedAt заполняется как момент завершения попытки генерации
    }
}
