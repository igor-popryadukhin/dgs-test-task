<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\PurchaseOrder;
use App\Entity\SupplierIssue;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<SupplierIssue> */
final class SupplierIssueRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SupplierIssue::class);
    }

    public function findByRequestId(string $requestId): ?SupplierIssue
    {
        return $this->find($requestId);
    }

    public function findByOrder(PurchaseOrder $order): ?SupplierIssue
    {
        return $this->findOneBy(['order' => $order]);
    }
}
