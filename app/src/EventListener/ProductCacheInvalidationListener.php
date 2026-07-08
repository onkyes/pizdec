<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\Product;
use App\Service\ProductListCacheService;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Events;

#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::postUpdate)]
#[AsDoctrineListener(event: Events::postRemove)]
final readonly class ProductCacheInvalidationListener
{
    public function __construct(
        private ProductListCacheService $productListCacheService,
    ) {}

    public function postPersist(PostPersistEventArgs $args): void
    {
        $this->invalidateIfProduct($args->getObject());
    }

    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $this->invalidateIfProduct($args->getObject());
    }

    public function postRemove(PostRemoveEventArgs $args): void
    {
        $this->invalidateIfProduct($args->getObject());
    }

    private function invalidateIfProduct(object $entity): void
    {
        if (!$entity instanceof Product) {
            return;
        }

        $this->productListCacheService->invalidate();
    }
}
