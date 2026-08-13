<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\BuyerOrderItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BuyerOrderItem>
 */
final class BuyerOrderItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BuyerOrderItem::class);
    }

    /**
     * @return iterable<BuyerOrderItem>
     */
    public function iterateSoldItemsForPeriod(
        \DateTimeImmutable $periodFrom,
        \DateTimeImmutable $periodTo,
    ): iterable {
        $queryBuilder = $this->createQueryBuilder('item')
            ->innerJoin('item.buyerOrder', 'buyerOrder')
            ->andWhere('buyerOrder.createdAt >= :periodFrom')
            ->andWhere('buyerOrder.createdAt < :periodTo')
            ->setParameter('periodFrom', $periodFrom)
            ->setParameter('periodTo', $periodTo)
            ->orderBy('buyerOrder.id', 'ASC')
            ->addOrderBy('item.id', 'ASC');

        return $queryBuilder
            ->getQuery()
            ->toIterable();
    }
}
