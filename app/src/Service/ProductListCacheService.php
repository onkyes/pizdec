<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\PaginationRequest;
use App\Entity\Product;
use App\Repository\ProductRepository;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * @phpstan-type ProductListItem array{
 *     id: int,
 *     name: string,
 *     description: string,
 *     price: int,
 *     weight: int,
 *     category: string
 * }
 */
final readonly class ProductListCacheService
{
    private const CACHE_TTL = 3_600; // 1 час время жизни кеша
    private const CACHE_KEY_PREFIX = 'products.list';

    public function __construct(
        private ProductRepository $productRepository,
        #[Autowire(service: 'product_list_cache')]
        private CacheInterface $productListCache,
        #[Autowire(service: 'product_list_cache')]
        private CacheItemPoolInterface $productListCachePool,
    ) {}

    /**
     * @return list<ProductListItem>
     */
    public function getProductList(PaginationRequest $pagination): array
    {
        $cacheKey = $this->getCacheKey($pagination); // ключ зависит от limit и offset

        return $this->productListCache->get(
            $cacheKey,
            function (ItemInterface $item) use ($pagination): array {
                $item->expiresAfter(self::CACHE_TTL); // кеш живёт 1 час

                return $this->loadProductList($pagination); // если кеш пустой, идём в бд
            },
        );
    }

    public function invalidate(): void
    {
        $this->productListCachePool->clear(); // очищаем кеш списка продуктов
    }

    private function getCacheKey(PaginationRequest $pagination): string
    {
        return \sprintf(
            '%s.limit.%d.offset.%d',
            self::CACHE_KEY_PREFIX,
            $pagination->limit,
            $pagination->getOffset(),
        );
    }

    /**
     * @return list<ProductListItem>
     */
    private function loadProductList(PaginationRequest $pagination): array
    {
        $products = $this->productRepository->findBy(
            [],
            ['id' => 'ASC'],
            $pagination->limit,
            $pagination->getOffset(),
        );

        $data = [];

        foreach ($products as $product) {
            $data[] = $this->serializeProduct($product);
        }

        return $data;
    }

    /**
     * @return ProductListItem
     */
    private function serializeProduct(Product $product): array
    {
        return [
            'id' => $product->getId(),
            'name' => $product->getName(),
            'description' => $product->getDescription(),
            'price' => $product->getPrice(),
            'weight' => $product->getWeight(),
            'category' => $product->getCategory(),
        ];
    }
}
