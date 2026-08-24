<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\BuyerOrderItem;
use App\Enum\OrderStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\AbstractQuery;
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
     * @return iterable<array{
     *     productName: string,
     *     productPrice: int,
     *     quantity: int,
     *     userId: int
     * }>
     */
    public function iterateSoldItemsForPeriod(
        \DateTimeImmutable $periodFrom,
        \DateTimeImmutable $periodTo,
    ): iterable {
        $queryBuilder = $this->createQueryBuilder('item')
            ->select(
                'item.productName AS productName',
                'item.productPrice AS productPrice',
                'item.quantity AS quantity',
                'owner.id AS userId',
            )
            ->innerJoin('item.buyerOrder', 'buyerOrder')
            ->innerJoin('buyerOrder.owner', 'owner')
            ->andWhere('buyerOrder.createdAt >= :periodFrom')
            ->andWhere('buyerOrder.createdAt < :periodTo')
            ->andWhere('buyerOrder.status IN (:statuses)')
            ->setParameter('periodFrom', $periodFrom)
            ->setParameter('periodTo', $periodTo)
            ->setParameter('statuses', [
                OrderStatus::Paid->value,
                OrderStatus::InProgress->value,
                OrderStatus::Delivering->value,
                OrderStatus::Completed->value,
            ])
            ->orderBy('buyerOrder.id', 'ASC')
            ->addOrderBy('item.id', 'ASC');

        return $queryBuilder
            ->getQuery()
            ->toIterable([], AbstractQuery::HYDRATE_ARRAY);
    }
}
