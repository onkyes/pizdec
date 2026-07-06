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
            fn(BuyerOrder $order): array => $this->serializeOrder($order),
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

    /**
     * @return array{
     *     id: int,
     *     status: string,
     *     total: int,
     *     deliveryType: string,
     *     deliveryAddress: array{
     *         region: string|null,
     *          city: string|null,
     *          street: string|null,
     *          house: string|null,
     *          entrance: string|null,
     *          apartment: string|null,
     *          postalCode: string|null
     *     },
     *     items: list<array{
     *         id: int,
     *         productId: int,
     *         productName: string,
     *         productPrice: int,
     *         productWeight: int,
     *         productCategory: string,
     *         quantity: int,
     *         lineTotal: int
     *     }>,
     *     createdAt: string,
     *     updatedAt: string
     * }
     */
    private function serializeOrder(BuyerOrder $order): array
    {
        $items = []; // сюда складываем товары заказа

        foreach ($order->getItems() as $item) {
            $items[] = [
                'id' => $item->getId(), // id строки заказа
                'productId' => $item->getProductId(), // id товара на момент заказа
                'productName' => $item->getProductName(), // название товара на момент заказа
                'productPrice' => $item->getProductPrice(), // цена товара на момент заказа
                'productWeight' => $item->getProductWeight(), // вес товара на момент заказа
                'productCategory' => $item->getProductCategory(), // категория товара на момент заказа
                'quantity' => $item->getQuantity(), // количество товара в заказе
                'lineTotal' => $item->getLineTotal(), // сумма строки заказа
            ];
        }

        return [
            'id' => $order->getId(),
            'status' => $order->getStatus()->value,
            'total' => $order->getTotal(),
            'deliveryType' => $order->getDeliveryType()->value, // способ получения заказа: pickup или courier
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
