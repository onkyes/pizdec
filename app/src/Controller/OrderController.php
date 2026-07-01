<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\CreateOrderRequest;
use App\Entity\BuyerOrder;
use App\Entity\User;
use App\Service\OrderService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

final class OrderController extends AbstractController
{
    #[Route('/api/orders', name: 'order_create', methods: ['POST'])]
    public function create(
        #[MapRequestPayload]
        CreateOrderRequest $dto,
        OrderService $orderService,
    ): JsonResponse {
        $user = $this->getUser(); // получаем текущего пользователя

        if (!$user instanceof User) {
            // если пользователя нет, значит запрос без авторизации
            return $this->json(['message' => 'Требуется авторизация'], Response::HTTP_UNAUTHORIZED);
        }

        $order = $orderService->createOrder($user, $dto);
        // передаём создание заказа в сервис

        return $this->json(
            $this->serializeOrder($order),
            Response::HTTP_CREATED,
        );
    }

    #[Route('/api/orders', name: 'order_index', methods: ['GET'])]
    public function index(
        OrderService $orderService,
    ): JsonResponse {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->json(['message' => 'Требуется авторизация'], Response::HTTP_UNAUTHORIZED);
        }

        $orders = $orderService->getUserOrders($user);

        $data = array_map(
            fn (BuyerOrder $order): array => $this->serializeOrder($order),
            $orders,
        );

        return $this->json(
            $data,
            Response::HTTP_OK,
        );
    }

    #[Route('/api/orders/{id}', name: 'order_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(
        int $id,
        OrderService $orderService,
    ): JsonResponse {
        $user = $this->getUser(); // получаем текущего пользователя

        if (!$user instanceof User) {
            // если пользователя нет, значит запрос без авторизации
            return $this->json(['message' => 'Требуется авторизация'], Response::HTTP_UNAUTHORIZED);
        }

        $order = $orderService->getUserOrder($user, $id);
        // ищем заказ и проверяем, что он принадлежит пользователю

        return $this->json(
            $this->serializeOrder($order),
            Response::HTTP_OK,
        );
    }

    private function serializeOrder(BuyerOrder $order): array
    {
        $items = []; // сюда складываем товары заказа

        foreach ($order->getItems() as $item) {
            $items[] = [
                'id' => $item->getId(),
                'productId' => $item->getProductId(),
                'productName' => $item->getProductName(),
                'productPrice' => $item->getProductPrice(),
                'productWeight' => $item->getProductWeight(),
                'productCategory' => $item->getProductCategory(),
                'quantity' => $item->getQuantity(),
                'lineTotal' => $item->getLineTotal(),
            ];
        }

        return [
            'id' => $order->getId(),
            'status' => $order->getStatus(),
            'total' => $order->getTotal(),
            'deliveryAddress' => [
                'region' => $order->getDeliveryRegion(),
                'city' => $order->getDeliveryCity(),
                'street' => $order->getDeliveryStreet(),
                'house' => $order->getDeliveryHouse(),
                'entrance' => $order->getDeliveryEntrance(),
                'apartment' => $order->getDeliveryApartment(),
                'postalCode' => $order->getDeliveryPostalCode(),
            ],
            'items' => $items,
            'createdAt' => $order->getCreatedAt()->format(DATE_ATOM),
            'updatedAt' => $order->getUpdatedAt()->format(DATE_ATOM),
        ];
    }
}
