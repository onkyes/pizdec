<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\CreateOrderRequest;
use App\Entity\Basket;
use App\Entity\BasketItem;
use App\Entity\BuyerOrder;
use App\Entity\BuyerOrderItem;
use App\Entity\User;
use App\Enum\DeliveryType;
use App\Enum\OrderStatus;
use App\Exception\TranslatableHttpException;
use App\Repository\BasketRepository;
use App\Repository\BuyerOrderRepository;
use Doctrine\DBAL\Exception\RetryableException;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;

final readonly class OrderService
{
    public function __construct(
        private BasketRepository $basketRepository,
        private BuyerOrderRepository $buyerOrderRepository,
        private BasketValidator $basketValidator,
        private EntityManagerInterface $em,
    ) {}

    public function createOrder(User $user, CreateOrderRequest $dto): BuyerOrder
    // оформляет заказ из корзины пользователя
    {
        try {
            return $this->em->wrapInTransaction(
                fn(): BuyerOrder => $this->createOrderInTransaction($user, $dto),
            );
            // открываем транзакцию и передаём основную логику создания заказа в отдельный метод
        } catch (RetryableException $e) {
            // ловим конфликт конкурентного обновления, если база не смогла безопасно выполнить операцию
            throw new TranslatableHttpException(
                'basket.locked',
                Response::HTTP_CONFLICT,
            );
            // отдаём понятную ошибку 409 вместо неожиданной ошибки базы
        }
    }

    /**
     * @return list<BuyerOrder>
     */
    public function getUserOrders(User $user): array
    // Возвращает список заказов текущего пользователя
    {
        return $this->buyerOrderRepository->findBy(
            ['owner' => $user], // ищем только заказы $user
            ['createdAt' => 'DESC'], // сортировка от новых к старым
        );
    }

    public function getUserOrder(User $user, int $orderId): BuyerOrder
    // Возвращает конкретный заказ пользователя
    {
        $order = $this->buyerOrderRepository->find($orderId);

        if ($order === null) {
            throw new TranslatableHttpException(
                'order.not_found',
                Response::HTTP_NOT_FOUND,
            );
        }

        if ($order->getOwner()->getId() !== $user->getId()) {
            throw new TranslatableHttpException(
                'order.not_found',
                Response::HTTP_NOT_FOUND,
            );
        }

        return $order;
    }

    private function createOrderInTransaction(User $user, CreateOrderRequest $dto): BuyerOrder
    // создаёт заказ внутри транзакции
    {
        $basket = $this->basketRepository->findOneBy([
            'owner' => $user,
        ]);
        // ищем корзину текущего пользователя в базе

        if ($basket === null) {
            throw new TranslatableHttpException(
                'basket.not_found',
                Response::HTTP_NOT_FOUND,
            );
        }
        // если корзины нет, заказ создать нельзя

        $this->em->lock($basket, LockMode::PESSIMISTIC_WRITE);
        // блокируем корзину на запись, пока создаём из неё заказ

        $this->basketValidator->assertBasketIsNotEmpty($basket);
        // проверяем, что корзина не пустая

        $basketItems = $basket->getItems()->toArray();
        // сохраняем список товаров из корзины в отдельный массив

        $total = $this->calculateBasketTotal($basket);
        // считаем общую сумму заказа по товарам в корзине

        $order = new BuyerOrder(
            $user, // пользователь, который оформляет заказ
            OrderStatus::Created, // новый заказ всегда создаётся со статусом created
            $total, // итоговая сумма заказа, которую посчитали по корзине
            DeliveryType::from($dto->deliveryType), // доставка?
            $dto->deliveryRegion, // область доставки из запроса
            $dto->deliveryCity, // город доставки из запроса
            $dto->deliveryStreet, // улица доставки из запроса
            $dto->deliveryHouse, // дом доставки из запроса
            $dto->deliveryPostalCode, // индекс доставки из запроса
            $dto->deliveryEntrance, // подъезд, может быть null
            $dto->deliveryApartment, // квартира, может быть null
        );

        $this->em->persist($order);
        // говорим doctrine, что новый заказ нужно сохранить в базу

        foreach ($basketItems as $basketItem) {
            // проходим по товарам, которые лежали в корзине на момент создания заказа

            $product = $basketItem->getProduct();
            // достаём продукт из позиции корзины

            $lineTotal = $this->calculateLineTotal($basketItem);
            // считаем сумму одной строки заказа: цена товара * количество

            $orderItem = new BuyerOrderItem(
                $order, // заказ, к которому относится эта строка
                $product->getId(), // сохраняем id товара на момент заказа
                $product->getName(), // сохраняем название товара на момент заказа
                $product->getPrice(), // сохраняем цену товара на момент заказа
                $product->getWeight(), // сохраняем вес товара на момент заказа
                $product->getCategory(), // сохраняем категорию товара на момент заказа
                $basketItem->getQuantity(), // сохраняем количество из корзины
                $lineTotal, // сохраняем сумму строки
            );

            $order->addItem($orderItem);
            // добавляем строку заказа в коллекцию заказа

            $this->em->persist($orderItem);
            // говорим doctrine, что строку заказа тоже нужно сохранить
        }

        foreach ($basketItems as $basketItem) {
            // после создания заказа очищаем корзину от тех товаров, которые ушли в заказ

            $basket->removeItem($basketItem);
            // убираем позицию из коллекции корзины

            $this->em->remove($basketItem);
            // говорим doctrine удалить эту позицию корзины из базы
        }

        return $order;
        // возвращает созданный заказ
    }

    private function calculateLineTotal(BasketItem $basketItem): int
    // считает сумму строки в корзине
    {
        $product = $basketItem->getProduct();

        return $product->getPrice() * $basketItem->getQuantity();

    }

    private function calculateBasketTotal(Basket $basket): int
    // считает общую сумму всей корзины
    {
        $total = 0;

        foreach ($basket->getItems() as $basketItem) {
            $total += $this->calculateLineTotal($basketItem);
        }

        return $total;
    }
}
