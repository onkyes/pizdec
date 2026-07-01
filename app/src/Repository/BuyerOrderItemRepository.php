<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\BuyerOrderItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BuyerOrderItem>
 */
class BuyerOrderItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BuyerOrderItem::class);
    }
}
