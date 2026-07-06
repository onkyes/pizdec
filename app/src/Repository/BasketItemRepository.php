<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\BasketItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BasketItem>
 */
final class BasketItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BasketItem::class);
    }
}
