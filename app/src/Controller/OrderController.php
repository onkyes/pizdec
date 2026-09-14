<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\CreateOrderRequest;
use App\Entity\BuyerOrder;
use App\Entity\User;
use App\Exception\TranslatableHttpException;
use App\Service\OrderService;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

final class OrderController extends AbstractController
{
    #[Route('/api/orders', name: 'order_create', methods: ['POST'])]
    #[OA\Post(
        description: 'Создаёт заказ из корзины авторизованного пользователя. '
        . 'Корзина должна содержать товары. '
        . 'После оформления корзина очищается. '
        . 'Для самовывоза адрес не нужен, для курьерской доставки обязателен.',
        summary: 'Оформить заказ',
        security: [['Bearer' => []]],
        tags: ['Orders'],
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            ref: new Model(type: CreateOrderRequest::class),
            examples: [
                new OA\Examples(
                    example: 'pickup',
                    summary: 'Самовывоз',
                    value: [
                        'deliveryType' => 'pickup',
                    ],
                ),
                new OA\Examples(
                    example: 'courier',
                    summary: 'Курьерская доставка',
                    value: [
                        'deliveryType' => 'courier',
                        'deliveryRegion' => 'Московская область',
                        'deliveryCity' => 'Химки',
                        'deliveryStreet' => 'Молодёжная',
                        'deliveryHouse' => '10',
                        'deliveryPostalCode' => '141400',
                    ],
                ),
            ],
        ),
    )]
    #[OA\Response(
        response: Response::HTTP_CREATED,
        description: 'Заказ создан',
        content: new OA\JsonContent(
            type: 'object',
            example: [
                'id' => 1,
                'status' => 'created',
                'total' => 1_000,
                'deliveryType' => 'pickup',
                'deliveryAddress' => [
                    'region' => null,
                    'city' => null,
                    'street' => null,
                    'house' => null,
                    'entrance' => null,
                    'apartment' => null,
                    'postalCode' => null,
                ],
                'items' => [
                    [
                        'id' => 1,
                        'productId' => 1,
                        'productName' => 'Маргарита',
                        'productPrice' => 500,
                        'productWeight' => 450,
                        'productCategory' => 'food',
                        'quantity' => 2,
                        'lineTotal' => 1_000,
                    ],
                ],
                'createdAt' => '2026-09-14T12:00:00+00:00',
                'updatedAt' => '2026-09-14T12:00:00+00:00',
            ],
        ),
    )]
    #[OA\Response(
        response: Response::HTTP_UNAUTHORIZED,
        description: 'Требуется авторизация',
        content: new OA\JsonContent(
            type: 'object',
            example: ['message' => 'Требуется авторизация'],
        ),
    )]
    #[OA\Response(
        response: Response::HTTP_NOT_FOUND,
        description: 'Корзина пользователя не найдена',
        content: new OA\JsonContent(
            type: 'object',
            example: ['message' => 'Корзина не найдена'],
        ),
    )]
    #[OA\Response(
        response: Response::HTTP_UNPROCESSABLE_ENTITY,
        description: 'Ошибка валидации полей запроса или пустая корзина',
        content: new OA\JsonContent(
            type: 'object',
            example: ['message' => 'Нельзя оформить заказ с пустой корзиной'],
        ),
    )]
    #[OA\Response(
        response: Response::HTTP_CONFLICT,
        description: 'Конфликт конкурентного изменения корзины',
        content: new OA\JsonContent(
            type: 'object',
            example: ['message' => 'Корзина сейчас обновляется. Повторите запрос.'],
        ),
    )]
    public function create(
        #[MapRequestPayload]
        CreateOrderRequest $dto,
        OrderService $orderService,
    ): JsonResponse {
        $user = $this->getUser(); // получаем текущего пользователя

        if (!$user instanceof User) {
            // если пользователя нет, значит запрос без авторизации
            throw new TranslatableHttpException('auth.required', Response::HTTP_UNAUTHORIZED);
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
            throw new TranslatableHttpException('auth.required', Response::HTTP_UNAUTHORIZED);
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
            throw new TranslatableHttpException('auth.required', Response::HTTP_UNAUTHORIZED);
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
