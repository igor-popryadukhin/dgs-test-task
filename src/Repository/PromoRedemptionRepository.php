<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\PromoRedemption;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<PromoRedemption> */
final class PromoRedemptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PromoRedemption::class);
    }
}
