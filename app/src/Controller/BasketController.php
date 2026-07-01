<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\AddBasketItemRequest;
use App\Dto\UpdateBasketItemRequest;
use App\Entity\Basket;
use App\Entity\User;
use App\Service\BasketService;
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
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->json(['message' => 'Требуется авторизация'], Response::HTTP_UNAUTHORIZED);
        }

        $basket = $basketService->getOrCreateBasket($user);

        return $this->json(
            $this->serializeBasket($basket),
            Response::HTTP_OK,
        );
    }

    #[Route('/api/basket/items', name: 'basket_item_add', methods: ['POST'])]
    public function add(
        #[MapRequestPayload]
        AddBasketItemRequest $dto,
        BasketService $basketService,
    ): JsonResponse { // Получаем текущего авторизованного пользователя
        $user = $this->getUser();

        if (!$user instanceof User) {// если пользователя нет - запрос без авторизации
            return $this->json(['message' => 'Требуется авторизация'], Response::HTTP_UNAUTHORIZED);
        }

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
        $user = $this->getUser();

        // Если пользователя нет, значит запрос без авторизации.
        if (!$user instanceof User) {
            return $this->json(['message' => 'Требуется авторизация'], Response::HTTP_UNAUTHORIZED);
        }

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
        $user = $this->getUser(); // Получаем текущего авторизованного пользователя.

        if (!$user instanceof User) {
            return $this->json(['message' => 'Требуется авторизация'], Response::HTTP_UNAUTHORIZED);
        }

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
        $user = $this->getUser(); // Получаем текущего авторизованного пользователя.

        // Если пользователя нет, значит запрос без авторизации.
        if (!$user instanceof User) {
            return $this->json(['message' => 'Требуется авторизация'], Response::HTTP_UNAUTHORIZED);
        }

        $basket = $basketService->clearBasket($user);

        return $this->json(
            $this->serializeBasket($basket),
            Response::HTTP_OK,
        );
    }

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
}
