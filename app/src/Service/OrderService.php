<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\CreateOrderRequest;
use App\Entity\Basket;
use App\Entity\BasketItem;
use App\Entity\BuyerOrder;
use App\Entity\BuyerOrderItem;
use App\Entity\User;
use App\Repository\BasketRepository;
use App\Repository\BuyerOrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class OrderService
{
    public function __construct(
        private BasketRepository $basketRepository,
        private BuyerOrderRepository $buyerOrderRepository,
        private BasketValidator $basketValidator,
        private EntityManagerInterface $em,
    ) {}

    public function createOrder(User $user, CreateOrderRequest $dto): BuyerOrder
    // оформляет заказ корзины
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

        $this->basketValidator->assertBasketIsNotEmpty($basket);

        $total = $this->calculateBasketTotal($basket);

        $order = new BuyerOrder(
            $user,
            BuyerOrder::STATUS_CREATED,
            $total,
            $dto->deliveryRegion,
            $dto->deliveryCity,
            $dto->deliveryStreet,
            $dto->deliveryHouse,
            $dto->deliveryPostalCode,
            $dto->deliveryEntrance,
            $dto->deliveryApartment,
        );

        $this->em->persist($order);

        foreach ($basket->getItems() as $basketItem) {
            $product = $basketItem->getProduct();

            $lineTotal = $this->calculateLineTotal($basketItem);

            $orderItem = new BuyerOrderItem(
                $order,
                $product->getId(),
                $product->getName(),
                $product->getPrice(),
                $product->getWeight(),
                $product->getCategory(),
                $basketItem->getQuantity(),
                $lineTotal,
            );

            $order->addItem($orderItem);

            $this->em->persist($orderItem);
        }

        foreach ($basket->getItems() as $basketItem) {
            $basket->removeItem($basketItem);
            $this->em->remove($basketItem);
        }

        $this->em->flush();

        return $order;
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
            throw new NotFoundHttpException('Заказ не найден');
        }

        if ($order->getOwner()->getId() !== $user->getId()) {
            throw new NotFoundHttpException('Заказ не найден');
        }

        return $order;
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
