<?php

namespace App\Repository;

use App\Entity\Order;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Order>
 */
class OrderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Order::class);
    }

    public function findOneByReference(string $reference): ?Order
    {
        return $this->findOneBy(['reference' => $reference]);
    }

    public function findOneByIdempotencyKey(string $key): ?Order
    {
        return $this->findOneBy(['idempotencyKey' => $key]);
    }

    /**
     * @return Order[] Les commandes d'un client, de la plus récente à la plus ancienne.
     */
    public function findByCustomerId(string $customerId): array
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.customerId = :cid')
            ->setParameter('cid', $customerId)
            ->orderBy('o.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
