<?php

declare(strict_types=1);

namespace App\Service;


use App\Dto\AddBasketItemRequest;
use App\Dto\UpdateBasketItemRequest;
use App\Entity\Basket;
use App\Entity\BasketItem;
use App\Entity\User;
use App\Repository\BasketItemRepository;
use App\Repository\BasketRepository;
use App\Repository\ProductRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\DBAL\Exception\RetryableException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;



final readonly class BasketService
{
    public function __construct(
        private BasketRepository       $basketRepository,
        private BasketItemRepository   $basketItemRepository,
        private ProductRepository      $productRepository,
        private EntityManagerInterface $em,
        private BasketValidator $basketValidator,
    ) {}

    public function getOrCreateBasket(User $user): Basket
        // метод или ищет корзину пользователя или создаёт новую
    {
        $basket = $this->basketRepository->findOneBy([
            'owner' => $user,
            // ищем корзину, где владелец — текущий пользователь
        ]);

        if ($basket !== null) {
            return $basket;
            //Если корзина уже есть, возвращаем её.
        }

        $basket = new Basket($user); //

        $this->em->persist($basket);
        $this->em->flush();

        return $basket;
    }

    public function addItem(User $user, AddBasketItemRequest $dto): Basket
        // метод "добавить товар в корзину". AddBasketItemRequest - данные из запроса
        // продуктАйди и quantity
    {
        try {
            return $this->em->wrapInTransaction(function () use ($user, $dto): Basket {

                $basket = $this->getOrCreateBasket($user);
                // получить корзину пользователя

                $this->em->lock($basket, LockMode::PESSIMISTIC_WRITE);
                // блокируем корзину на запись внутри транзакции

                $product = $this->productRepository->getById($dto->productId);
                // ищем товар. ProductRepository отвечает за поиск товаров в базе

                $allowedQuantity = $this->basketValidator->getAllowedQuantityToAdd(
                    $basket, // корзина пользователя
                    $product, // товар, который хотим добавить
                    $dto->quantity, // количество из запроса
                );
                // считаем, сколько товара реально можно добавить с учётом лимита категории

                if ($allowedQuantity <= 0) {
                    // если лимит уже достигнут, ничего не добавляем
                    return $basket;
                }

                $basketItem = $this->basketItemRepository->findOneBy([
                    'basket' => $basket,
                    'product' => $product,
                ]);

                if ($basketItem !== null) {
                    // если позиция уже есть в корзине, новый товар
                    // не добавляем - увеличиваем количество
                    $newQuantity = $basketItem->getQuantity() + $allowedQuantity;
                    // берём старое количество и прибавляем кол-во из запроса

                    $basketItem->setQuantity($newQuantity);
                    // записываем новое кол-во в объект

                    $this->touchBasket($basket);

                    return $basket;
                }

                $basketItem = new BasketItem($basket, $product, $allowedQuantity);
                // такого товара в корзине не нашлось, создаём
                // новую позицию корзины (чья корзина, какой товар, кол-во)

                $basket->addItem($basketItem);
                // добавляем товар в коллекцию корзины

                $this->touchBasket($basket);

                $this->em->persist($basketItem);

                return $basket;
            });
        } catch (RetryableException $e) {
            throw new ConflictHttpException('Корзина сейчас обновляется. Повторите запрос.',
            $e,
            );
        }
    }

    public function updateItemQuantity(
        User                    $user,
        int                     $itemId,
        UpdateBasketItemRequest $dto,
    ): Basket {
        try {
            return $this->em->wrapInTransaction(function () use ($user, $itemId, $dto): Basket {
                // получаем корзину текущего пользователя
                // если корзины нет - 404
                $basket = $this->getExistingBasket($user);

                $this->em->lock($basket, LockMode::PESSIMISTIC_WRITE);
                // блокируем корзину на запись внутри транзакции

                // находим позицию корзины по айди т и сразу проверяем,
                // что эта позиция принадлежит корзине текущего пользователя
                $basketItem = $this->getBasketItem($basket, $itemId);

                // проверяем, не превысит ли новое количество лимит категории
                $this->basketValidator->assertCanUpdateItem($basketItem, $dto->quantity);

                // записываем новое количество из дто
                // дто уже проверил, что quantity — число больше 0
                $basketItem->setQuantity($dto->quantity);

                // обновляем дату изменения всей корзины,
                // потому что её содержимое поменялось
                $this->touchBasket($basket);

                // Возвращаем обновлённую корзину
                return $basket;
            });
        } catch (RetryableException $e) {
            throw new ConflictHttpException(
                'Корзина сейчас обновляется. Повторите запрос.',
                $e,
            );
        }
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
            throw new NotFoundHttpException('Позиция корзины не найдена');
        }

        // Если позиция есть, но она из чужой корзины,
        // тоже отдаём 404, чтобы не раскрывать чужие данные
        if ($basketItem->getBasket() !== $basket) {
            throw new NotFoundHttpException('Позиция корзины не найдена');
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
            throw new NotFoundHttpException('Корзина не найдена');
        }

        // Если корзина есть, возвращаем её.
        return $basket;
    }

}


