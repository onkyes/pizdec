<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\PaginationRequest;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

#[AsDecorator(decorates: ProductListProvider::class)]
#[AsAlias(ProductListProviderInterface::class)]
final readonly class CachedProductListProvider implements ProductListProviderInterface
{
    private const CACHE_KEY_PREFIX = 'products.list';

    public function __construct(
        #[AutowireDecorated]
        private ProductListProviderInterface $inner,
        #[Autowire(service: 'product_list_cache')]
        private CacheInterface $productListCache,
        #[Autowire(service: 'product_list_cache')]
        private CacheItemPoolInterface $productListCachePool,
        #[Autowire(env: 'int:PRODUCT_LIST_CACHE_TTL')]
        private int $cacheTtl,
    ) {}

    public function getProductList(PaginationRequest $pagination): array
    {

        return $this->productListCache->get(
            $this->getCacheKey($pagination),
            function (ItemInterface $item) use ($pagination): array {
                $item->expiresAfter($this->cacheTtl);

                return $this->inner->getProductList($pagination); // если кеш пустой, идём в бд
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
}
