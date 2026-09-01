<?php

declare(strict_types=1);

namespace App\Repository;

use App\Domain\OrderStatus;
use App\Entity\PurchaseOrder;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<PurchaseOrder> */
final class PurchaseOrderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PurchaseOrder::class);
    }

    public function findByClientRequestId(string $clientRequestId): ?PurchaseOrder
    {
        return $this->findOneBy(['clientRequestId' => $clientRequestId]);
    }

    public function findForUpdate(string $orderId): ?PurchaseOrder
    {
        $lockedOrderId = $this->getEntityManager()->getConnection()->fetchOne(
            'SELECT id FROM purchase_order WHERE id = :orderId FOR UPDATE',
            ['orderId' => $orderId],
        );

        return false === $lockedOrderId ? null : $this->find((string) $lockedOrderId);
    }

    /** @return list<PurchaseOrder> */
    public function findRecoverable(): array
    {
        return $this->createQueryBuilder('purchaseOrder')
            ->addSelect('product', 'deliveryAttempt')
            ->innerJoin('purchaseOrder.product', 'product')
            ->leftJoin('purchaseOrder.deliveryAttempt', 'deliveryAttempt')
            ->andWhere('purchaseOrder.status IN (:statuses)')
            ->setParameter('statuses', [OrderStatus::OutOfStock, OrderStatus::DeliveryFailed])
            ->orderBy('purchaseOrder.updatedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
