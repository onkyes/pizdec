<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\Product;
use App\Service\CachedProductListProvider;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Events;

#[AsEntityListener(event: Events::postPersist, entity: Product::class)]
#[AsEntityListener(event: Events::postUpdate, entity: Product::class)]
#[AsEntityListener(event: Events::postRemove, entity: Product::class)]
final readonly class ProductCacheInvalidationListener
{
    public function __construct(
        private CachedProductListProvider $cachedProductListProvider,
    ) {}

    public function postPersist(Product $product, PostPersistEventArgs $args): void
    {
        $this->cachedProductListProvider->invalidate();
    }

    public function postUpdate(Product $product, PostUpdateEventArgs $args): void
    {
        $this->cachedProductListProvider->invalidate();
    }

    public function postRemove(Product $product, PostRemoveEventArgs $args): void
    {
        $this->cachedProductListProvider->invalidate();
    }
}
