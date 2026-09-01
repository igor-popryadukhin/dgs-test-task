<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\PromoCode;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<PromoCode> */
final class PromoCodeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PromoCode::class);
    }

    public function claimUsage(string $code): ?PromoCode
    {
        $claimed = $this->getEntityManager()->getConnection()->executeStatement(<<<'SQL'
            UPDATE promo_code
            SET used_count = used_count + 1
            WHERE code = :code AND active = TRUE AND used_count < max_uses
        SQL, ['code' => $code]);

        if (1 !== $claimed) {
            return null;
        }

        return $this->find($code) ?? throw new \LogicException('Claimed promo code could not be reloaded.');
    }
}
