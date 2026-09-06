<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\AddBasketItemRequest;
use App\Dto\UpdateBasketItemRequest;
use App\Entity\Basket;
use App\Entity\BasketItem;
use App\Entity\User;
use App\Exception\TranslatableHttpException;
use App\Repository\BasketItemRepository;
use App\Repository\BasketRepository;
use App\Repository\ProductRepository;
use Doctrine\DBAL\Exception\RetryableException;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;

final readonly class BasketService
{
    public function __construct(
        private BasketRepository $basketRepository,
        private BasketItemRepository $basketItemRepository,
        private ProductRepository $productRepository,
        private EntityManagerInterface $em,
        private BasketValidator $basketValidator,
    ) {}

    public function getOrCreateBasket(User $user): Basket
    // ищет корзину пользователя или создаёт новую безопасно для параллельных запросов
    {
        try {
            return $this->em->wrapInTransaction(
                fn(): Basket => $this->getOrCreateBasketWithLockedOwner($user),
            );
            // открываем транзакцию и вызываем хелпер, который умеет безопасно создать корзину
        } catch (RetryableException $e) {
            throw new TranslatableHttpException(
                'basket.locked',
                Response::HTTP_CONFLICT,
            );
            // если база словила конфликт параллельного запроса, отдаём понятную ошибку 409
        }
    }

    public function addItem(User $user, AddBasketItemRequest $dto): Basket
    // метод добавляет товар в корзину
    // AddBasketItemRequest хранит productId и quantity из запроса
    {
        try {
            return $this->em->wrapInTransaction(
                fn(): Basket => $this->addItemInTransaction($user, $dto),
            );
            // открываем транзакцию и передаём основную логику в отдельный метод
        } catch (RetryableException $e) {
            throw new TranslatableHttpException(
                'basket.locked',
                Response::HTTP_CONFLICT,
            );
            // если база словила конфликт параллельного обновления, отдаём  409
        }
    }

    private function addItemInTransaction(User $user, AddBasketItemRequest $dto): Basket
    // содержит основную логику добавления товара внутри транзакции
    {
        $basket = $this->getOrCreateBasketWithLockedOwner($user);
        // безопасно получаем или создаём корзину с lock по пользователю

        $this->em->lock($basket, LockMode::PESSIMISTIC_WRITE);
        // блокируем корзину на запись, чтобы параллельные запросы не меняли её одновременно

        $product = $this->productRepository->getById($dto->productId);
        // ищем товар по id из запроса

        $allowedQuantity = $this->basketValidator->getAllowedQuantityToAdd(
            $basket,
            $product,
            $dto->quantity,
        );
        // считаем, сколько товара реально можно добавить с учётом лимита категории

        if ($allowedQuantity <= 0) {
            return $basket;
            // если лимит уже достигнут, ничего не добавляем
        }

        $basketItem = $this->basketItemRepository->findOneBy([
            'basket' => $basket,
            'product' => $product,
        ]);
        // проверяем, есть ли уже такой товар в этой корзине

        if ($basketItem !== null) {
            $newQuantity = $basketItem->getQuantity() + $allowedQuantity;
            // если позиция уже есть, увеличиваем количество

            $basketItem->setQuantity($newQuantity);
            // сохраняем новое количество в объекте

            $this->touchBasket($basket);
            // обновляем дату изменения корзины

            return $basket;
        }

        $basketItem = new BasketItem($basket, $product, $allowedQuantity);
        // если такого товара ещё нет, создаём новую позицию корзины

        $basket->addItem($basketItem);
        // добавляем позицию в коллекцию корзины

        $this->touchBasket($basket);
        // обновляем дату изменения корзины

        $this->em->persist($basketItem);
        // говорим doctrine сохранить новую позицию

        return $basket;
    }

    public function updateItemQuantity(
        User $user,
        int $itemId,
        UpdateBasketItemRequest $dto,
    ): Basket {
        try {
            return $this->em->wrapInTransaction(
                fn(): Basket => $this->updateItemQuantityInTransaction($user, $itemId, $dto),
            ); // запускаем обновление количества внутри транзакции
        } catch (RetryableException $e) {
            throw new TranslatableHttpException(
                'basket.locked',
                Response::HTTP_CONFLICT,
            ); // если база словила конфликт параллельного обновления, отдаём 409
        }
    }

    private function updateItemQuantityInTransaction(
        User $user,
        int $itemId,
        UpdateBasketItemRequest $dto,
    ): Basket {
        $basket = $this->getExistingBasket($user);
        // получаем существующую корзину пользователя
        // если корзины нет, будет 404

        $this->em->lock($basket, LockMode::PESSIMISTIC_WRITE);
        // блокируем корзину на запись внутри транзакции
        // второй параллельный запрос будет ждать

        $basketItem = $this->getBasketItem($basket, $itemId);
        // ищем позицию корзины по id
        // внутри метода ещё проверяется, что позиция принадлежит этой корзине

        $this->basketValidator->assertCanUpdateItem($basketItem, $dto->quantity);
        // проверяем, не превысит ли новое количество лимит категории

        $basketItem->setQuantity($dto->quantity);
        // записываем новое количество из запроса

        $this->touchBasket($basket);
        // обновляем дату изменения корзины

        return $basket;
        // возвращаем обновлённую корзину
    }

    public function removeItem(User $user, int $itemId): Basket
    {
        // Получаем корзину текущего пользователя
        $basket = $this->getExistingBasket($user);

        // Находим позицию корзины и проверяем, что она из этой корзины
        $basketItem = $this->getBasketItem($basket, $itemId);

        // Убираем позицию из коллекции корзины в PHP-объекте.
        // Это важно, потому что метод возвращает эту же корзину
        $basket->removeItem($basketItem);

        // Говорим Doctrine удалить эту позицию из базы.
        $this->em->remove($basketItem);

        // Обновляем дату изменения корзины
        $this->touchBasket($basket);

        // Отправляем удаление и обновление даты в базу
        $this->em->flush();

        // Возвращаем корзину уже без удалённой позиции
        return $basket;
    }

    public function clearBasket(User $user): Basket
    {
        // получаем существующую корзину пользователя
        // если корзины нет, будет 404
        $basket = $this->getExistingBasket($user);

        // проходим по всем позициям корзины
        foreach ($basket->getItems() as $basketItem) {
            // убираем позицию из коллекции корзины в PHP-объекте
            $basket->removeItem($basketItem);

            // говорим Doctrine удалить эту позицию из базы
            $this->em->remove($basketItem);
        }

        // обновляем дату изменения корзины,
        // потому что её содержимое поменялось
        $this->touchBasket($basket);

        // сохраняем удаления в базу
        $this->em->flush();

        // возвращаем уже пустую корзину
        return $basket;
    }

    private function getBasketItem(Basket $basket, int $itemId): BasketItem // хелпер
    {
        // Ищем позицию корзины по её id.
        $basketItem = $this->basketItemRepository->find($itemId);

        // Если позиции с таким id нет, отдаём 404
        if ($basketItem === null) {
            throw new TranslatableHttpException(
                'basket.item_not_found',
                Response::HTTP_NOT_FOUND,
            );
        }

        // Если позиция есть, но она из чужой корзины,
        // тоже отдаём 404, чтобы не раскрывать чужие данные
        if ($basketItem->getBasket() !== $basket) {
            throw new TranslatableHttpException(
                'basket.item_not_found',
                Response::HTTP_NOT_FOUND,
            );
        }

        // Если всё хорошо, возвращаем найденную позицию
        return $basketItem;
    }

    private function touchBasket(Basket $basket): void // хелпер
    {
        $basket->setUpdatedAt(new \DateTimeImmutable());
    }

    private function getExistingBasket(User $user): Basket
    {
        // Ищем корзину текущего пользователя в базе.
        $basket = $this->basketRepository->findOneBy([
            'owner' => $user,
        ]);

        // Если корзины нет, отдаём 404.
        // Для update/delete не надо создавать новую пустую корзину.
        if ($basket === null) {
            throw new TranslatableHttpException(
                'basket.not_found',
                Response::HTTP_NOT_FOUND,
            );
        }

        // Если корзина есть, возвращаем её.
        return $basket;
    }

    private function getOrCreateBasketWithLockedOwner(User $user): Basket
    // безопасно ищет или создаёт корзину внутри транзакции
    {
        $basket = $this->basketRepository->findOneBy([
            'owner' => $user,
        ]); // сначала пробуем найти корзину без блокировки

        if ($basket !== null) {
            return $basket; // если корзина уже есть, просто возвращаем её
        }

        $this->em->lock($user, LockMode::PESSIMISTIC_WRITE);
        // блокируем строку пользователя, чтобы второй параллельный запрос подождал

        $basket = $this->basketRepository->findOneBy([
            'owner' => $user,
        ]); // после блокировки проверяем ещё раз

        if ($basket !== null) {
            return $basket; // другой запрос мог создать корзину, пока мы ждали lock
        }

        $basket = new Basket($user); // корзины всё ещё нет, значит создаём новую

        $this->em->persist($basket); // говорим doctrine сохранить корзину
        $this->em->flush();

        return $basket;
    }
}
