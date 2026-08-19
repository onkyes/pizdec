<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\CreateReportRequest;
use App\Dto\ReportResponse;
use App\Entity\Report;
use App\Enum\ReportStatus;
use App\Message\GenerateReportMessage;
use App\Repository\ReportRepository;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')] // пускаем только админа
final class ReportController extends AbstractController // отчёты
{
    #[Route('/api/reports', name: 'report_create', methods: ['POST'])] // POST /api/reports
    public function create(// метод создания отчёта
        #[MapRequestPayload] // берём данные из json
        CreateReportRequest $dto, // dto с периодом отчёта
        EntityManagerInterface $em, // entity manager для сохранения
        MessageBusInterface $messageBus, // bus для отправки задачи
    ): JsonResponse { // возвращаем json
        try { // ловим ошибки дат
            $report = new Report( // создаём отчёт
                new \DateTimeImmutable($dto->periodFrom), // дата начала периода
                new \DateTimeImmutable($dto->periodTo), // дата конца периода
            );
        } catch (\Throwable $e) { // если дата битая или период неверный
            return $this->json( // возвращаем ошибку json
                ['message' => $e->getMessage()], // текст ошибки
                Response::HTTP_BAD_REQUEST, // код 400
            );
        }

        $em->persist($report); // говорим doctrine сохранить отчёт
        $em->flush(); // реально пишем в бд, тут появляется id

        $messageBus->dispatch(new GenerateReportMessage($report->getId())); // отправляем задачу в очередь

        return $this->json( // возвращаем ответ клиенту
            ReportResponse::fromEntity($report), // превращаем отчёт в массив
            Response::HTTP_CREATED, // код 201
        );
    }

    #[Route('/api/reports/{id}', name: 'report_show', requirements: ['id' => '\d+'], methods: ['GET'])] // GET /api/reports/1
    public function show(// метод показа отчёта
        int $id, // id отчёта из url
        ReportRepository $reportRepository, // репозиторий отчётов
    ): JsonResponse { // возвращаем json
        $report = $reportRepository->find($id); // ищем отчёт по id

        if ($report === null) { // если отчёта нет
            return $this->json( // возвращаем json с ошибкой
                ['message' => 'Отчёт не найден'], // текст ошибки
                Response::HTTP_NOT_FOUND, // код 404
            );
        }

        return $this->json( // возвращаем json с отчётом
            ReportResponse::fromEntity($report), // превращаем отчёт в массив
            Response::HTTP_OK, // код 200
        );
    }

    #[Route('/api/reports/{id}/download', name: 'report_download', requirements: ['id' => '\d+'], methods: ['GET'])] // GET /api/reports/1/download
    public function download(// метод скачивания отчёта
        int $id, // id отчёта из url
        ReportRepository $reportRepository, // репозиторий отчётов
        #[Autowire(service: 'reports.storage')] // берём storage для отчётов
        FilesystemOperator $reportsStorage, // flysystem storage, за ним MinIO
    ): Response { // возвращаем обычный response
        $report = $reportRepository->find($id); // ищем отчёт по id

        if ($report === null) { // если отчёта нет
            return $this->json( // возвращаем json с ошибкой
                ['message' => 'Отчёт не найден'], // текст ошибки
                Response::HTTP_NOT_FOUND, // код 404
            );
        }

        if ($report->getStatus() !== ReportStatus::Completed || $report->getFilePath() === null) { // если отчёт ещё не готов
            return $this->json( // возвращаем json с ошибкой
                ['message' => 'Отчёт ещё не готов'], // текст ошибки
                Response::HTTP_CONFLICT, // код 409
            );
        }

        try {
            $stream = $reportsStorage->readStream($report->getFilePath());
        } catch (FilesystemException) {
            return $this->json(
                ['message' => 'Файл отчёта не найден'],
                Response::HTTP_NOT_FOUND,
            );
        }

        return new StreamedResponse( // отдаём файл потоком
            static function () use ($stream): void { // функция, которая пишет файл в ответ
                fpassthru($stream); // выводим содержимое файла
                fclose($stream); // закрываем поток
            },
            Response::HTTP_OK, // код 200
            [
                'Content-Type' => 'application/x-ndjson', // тип jsonl/ndjson
                'Content-Disposition' => 'attachment; filename="' . basename($report->getFilePath()) . '"', // имя файла
            ],
        );
    }
}
