<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\DeliveryAttempt;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<DeliveryAttempt> */
final class DeliveryAttemptRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DeliveryAttempt::class);
    }
}
