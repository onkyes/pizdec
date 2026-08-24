<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\OutboxMessage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<OutboxMessage>
 */
final class OutboxMessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OutboxMessage::class);
    }

    /**
     * @return list<OutboxMessage>
     */
    public function findUnpublished(int $limit): array // ищем сообщения, которые ещё не отправили
    {
        return $this->createQueryBuilder('outboxMessage')
            ->andWhere('outboxMessage.publishedAt IS NULL') // берём только неотправленные
            ->orderBy('outboxMessage.id', 'ASC') // сначала старые сообщения
            ->setMaxResults($limit) // ограничиваем пачку
            ->getQuery()
            ->getResult();
    }
}
