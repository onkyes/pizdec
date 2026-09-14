<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\AddBasketItemRequest;
use App\Dto\UpdateBasketItemRequest;
use App\Entity\Basket;
use App\Entity\User;
use App\Exception\TranslatableHttpException;
use App\Service\BasketService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

final class BasketController extends AbstractController
{
    #[Route('/api/basket', name: 'basket_show', methods: ['GET'])]
    public function show(
        BasketService $basketService,
    ): JsonResponse {
        $user = $this->getAuthenticatedUser(); // получаем авторизованного пользователя или сразу отдаём 401

        $basket = $basketService->getOrCreateBasket($user);

        return $this->json(
            $this->serializeBasket($basket),
            Response::HTTP_OK,
        );
    }

    #[Route('/api/basket/items', name: 'basket_item_add', methods: ['POST'])]
    #[OA\Post(
        description: 'Добавляет товар в корзину текущего пользователя. '
        . 'Требуется JWT-авторизация. '
        . 'Если товар уже есть, его количество увеличивается. '
        . 'Количество ограничивается остатком до лимита категории: '
        . '10 единиц еды или 20 напитков. '
        . 'При достигнутом лимите корзина остаётся без изменений; ответ — 200.',
        summary: 'Добавить товар в корзину',
        security: [['Bearer' => []]],
        tags: ['Basket'],
    )]
    #[OA\Response(
        response: Response::HTTP_OK,
        description: 'Корзина после обработки запроса',
        content: new OA\JsonContent(
            required: ['id', 'items', 'total'],
            properties: [
                new OA\Property(
                    property: 'id',
                    description: 'ID корзины',
                    type: 'integer',
                    example: 1,
                ),
                new OA\Property(
                    property: 'items',
                    type: 'array',
                    items: new OA\Items(
                        required: [
                            'id', 'productId', 'productName',
                            'price', 'quantity', 'lineTotal',
                        ],
                        properties: [
                            new OA\Property(
                                property: 'id',
                                description: 'ID позиции корзины',
                                type: 'integer',
                                example: 1,
                            ),
                            new OA\Property(
                                property: 'productId',
                                type: 'integer',
                                example: 1,
                            ),
                            new OA\Property(
                                property: 'productName',
                                type: 'string',
                                example: 'Маргарита',
                            ),
                            new OA\Property(
                                property: 'price',
                                type: 'integer',
                                example: 500,
                            ),
                            new OA\Property(
                                property: 'quantity',
                                type: 'integer',
                                example: 2,
                            ),
                            new OA\Property(
                                property: 'lineTotal',
                                description: 'Цена товара × количество',
                                type: 'integer',
                                example: 1_000,
                            ),
                        ],
                        type: 'object',
                    ),
                ),
                new OA\Property(
                    property: 'total',
                    description: 'Общая стоимость корзины',
                    type: 'integer',
                    example: 1_000,
                ),
            ],
            type: 'object',
        ),
    )]
    #[OA\Response(
        response: Response::HTTP_UNAUTHORIZED,
        description: 'Требуется авторизация',
        content: new OA\JsonContent(
            required: ['message'],
            properties: [
                new OA\Property(
                    property: 'message',
                    type: 'string',
                    example: 'Требуется авторизация',
                ),
            ],
            type: 'object',
        ),
    )]
    #[OA\Response(
        response: Response::HTTP_NOT_FOUND,
        description: 'Товар с указанным productId не найден',
    )]
    #[OA\Response(
        response: Response::HTTP_UNPROCESSABLE_ENTITY,
        description: 'Ошибка валидации productId или quantity; '
        . 'также возможна неизвестная категория товара.',
    )]
    #[OA\Response(
        response: Response::HTTP_CONFLICT,
        description: 'Конфликт конкурентного изменения корзины. Повторите запрос.',
    )]
    public function add(
        #[MapRequestPayload]
        AddBasketItemRequest $dto,
        BasketService $basketService,
    ): JsonResponse { // Получаем текущего авторизованного пользователя
        $user = $this->getAuthenticatedUser();

        // передаём работу сервису. Он ищет корзину и товар и добавляет позицию
        $basket = $basketService->addItem($user, $dto);

        // возвращаем обновлённую корзину в JSON
        return $this->json(
            $this->serializeBasket($basket),
            Response::HTTP_OK,
        );
    }

    #[Route('/api/basket/items/{id}', name: 'basket_item_update', requirements: ['id' => '\d+'], methods: ['PATCH'])]
    public function update(
        int $id,
        #[MapRequestPayload]
        UpdateBasketItemRequest $dto,
        BasketService $basketService,
    ): JsonResponse {
        // Получаем текущего авторизованного пользователя.
        $user = $this->getAuthenticatedUser();

        // Передаём работу сервису:$user = чей пользователь меняет корзину, $id = id позиции корзины из URL, $dto = новое количество из JSON.
        $basket = $basketService->updateItemQuantity($user, $id, $dto);

        // Возвращаем обновлённую корзину в JSON.
        return $this->json(
            $this->serializeBasket($basket),
            Response::HTTP_OK,
        );
    }

    #[Route('/api/basket/items/{id}', name: 'basket_item_delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function delete(
        int $id,
        BasketService $basketService,
    ): JsonResponse {
        $user = $this->getAuthenticatedUser();

        $basket = $basketService->removeItem($user, $id);

        return $this->json(
            $this->serializeBasket($basket),
            Response::HTTP_OK,
        );
    }

    #[Route('/api/basket/items', name: 'basket_clear', methods: ['DELETE'])]
    public function clear(
        BasketService $basketService,
    ): JsonResponse {
        $user = $this->getAuthenticatedUser();

        $basket = $basketService->clearBasket($user);

        return $this->json(
            $this->serializeBasket($basket),
            Response::HTTP_OK,
        );
    }

    /**
     * @return array{
     *     id: int,
     *     items: list<array{
     *         id: int,
     *         productId: int,
     *         productName: string,
     *         price: int,
     *         quantity: int,
     *         lineTotal: int
     *     }>,
     *     total: int
     * }
     */
    private function serializeBasket(Basket $basket): array
    {
        $items = []; // сюда будем складывать товары корзины
        $total = 0; // тут будем считать общую сумму корзины

        foreach ($basket->getItems() as $item) { // проходим по каждой позиции корзины
            $product = $item->getProduct(); // получаем товар из позиции корзины

            $lineTotal = $product->getPrice() * $item->getQuantity(); // считаем сумму текущей строки

            $items[] = [ // добавляем позицию корзины в массив для JSON-ответа

                'id' => $item->getId(), // айди позиции корзины, нужен для патч\делит конкретной позиции
                'productId' => $product->getId(),
                'productName' => $product->getName(),
                'price' => $product->getPrice(),
                'quantity' => $item->getQuantity(),
                'lineTotal' => $lineTotal,
            ];


            $total += $lineTotal; // прибавляем сумму строки к общей сумме корзины
        }

        return [ // возвращаем корзину
            'id' => $basket->getId(),
            'items' => $items, // все позиции корзины
            'total' => $total, // общая сумма корзины
        ];
    }

    private function getAuthenticatedUser(): User
    // возвращает текущего пользователя или кидает ошибку 401
    {
        $user = $this->getUser(); // достаём пользователя из security

        if (!$user instanceof User) {
            // если пользователя нет, значит запрос пришёл без нормального токена
            throw new TranslatableHttpException('auth.required', Response::HTTP_UNAUTHORIZED);
        }

        return $user; // возвращаем User, чтобы дальше не проверять instanceof в каждом методе
    }
}
