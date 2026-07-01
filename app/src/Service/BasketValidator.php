<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Basket;
use App\Entity\BasketItem;
use App\Entity\Product;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final readonly class BasketValidator
{
    private const MAX_FOOD_COUNT = 10;
    private const MAX_DRINK_COUNT = 20;
    private const FOOD_CATEGORY = 'food';
    private const DRINK_CATEGORY = 'drink';

    public function getAllowedQuantityToAdd(
        Basket $basket,
        Product $product,
        int $quantityToAdd,
    ): int {
        $category = $product->getCategory(); // взять категорию товара

        $currentCategoryCount = $this->countItemsByCategory($basket, $category); // считаем сколько
        // товаров уже есть в корзине

        $maxCategoryCount = $this->getMaxCountForCategory($category);
        // получаем лимит для этой категории

        $availableCount = $maxCategoryCount - $currentCategoryCount; // СКОЛЬКО МОЖНО ДОБАВИТЬ

        if ($availableCount <= 0) {
            return 0; // если лимит достигнут - больше не добавить
        }

        return min($quantityToAdd, $availableCount); // если овер добавили, то добавляем до лимита
    }

    public function assertCanUpdateItem(BasketItem $basketItem, int $newQuantity): void
    {
        $basket = $basketItem->getBasket(); // берём корзину, в которой лежит эта позиция

        $product = $basketItem->getProduct(); // берём продукт из этой позиции корзины

        $category = $product->getCategory(); // берём категорию продукта: food или drink

        $currentCategoryCount = $this->countItemsByCategory($basket, $category);
        // считаем, сколько товаров этой категории сейчас лежит в корзине

        $currentItemQuantity = $basketItem->getQuantity(); // берём старое количество этой позиции

        $categoryCountWithoutCurrentItem = $currentCategoryCount - $currentItemQuantity;
        // убираем старое количество текущей позиции из общего количества категории
        // так мы понимаем, сколько товаров этой категории останется без неё

        $newCategoryCount = $categoryCountWithoutCurrentItem + $newQuantity;
        // прибавляем новое количество, которое хочет поставить пользователь
        // получаем итоговое количество товаров этой категории после обновления

        $maxCategoryCount = $this->getMaxCountForCategory($category);
        // получаем лимит для этой категории: food = 10, drink = 20

        if ($newCategoryCount > $maxCategoryCount) {
            // если итоговое количество стало больше лимита, обновлять нельзя
            throw new UnprocessableEntityHttpException('Превышен лимит товаров этой категории');
        }
    }

    public function assertBasketIsNotEmpty(Basket $basket): void
    {
        if ($basket->getItems()->isEmpty()) {
            throw new UnprocessableEntityHttpException('Нельзя оформить заказ с пустой корзиной');
        }
    }

    private function countItemsByCategory(Basket $basket, string $category): int
    // метод считает, сколько товаров конкретной категории в корзине
    {
        $count = 0;

        foreach ($basket->getItems() as $item) {
            if ($item->getProduct()->getCategory() !== $category) {
                continue; // не та категория - пропускаем
            }

            $count += $item->getQuantity(); // совпала категория
        }

        return $count;
    }

    private function getMaxCountForCategory(string $category): int
    {
        return match ($category) {
            self::FOOD_CATEGORY => self::MAX_FOOD_COUNT,
            self::DRINK_CATEGORY => self::MAX_DRINK_COUNT,
            default => throw new UnprocessableEntityHttpException('Неизвестная категория товара'),
        };
    }
}
