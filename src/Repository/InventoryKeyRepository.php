<?php

declare(strict_types=1);

namespace App\Repository;

use App\Domain\SupplierProvider;
use App\Entity\InventoryKey;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<InventoryKey> */
final class InventoryKeyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InventoryKey::class);
    }

    public function claimAvailable(SupplierProvider $provider, string $sku): ?InventoryKey
    {
        $inventoryKeyId = $this->getEntityManager()->getConnection()->fetchOne(<<<'SQL'
            SELECT id
            FROM inventory_key
            WHERE provider = :provider AND sku = :sku AND assigned_order_id IS NULL
            ORDER BY id
            FOR UPDATE SKIP LOCKED
            LIMIT 1
        SQL, ['provider' => $provider->value, 'sku' => $sku]);

        if (false === $inventoryKeyId) {
            return null;
        }

        return $this->find((int) $inventoryKeyId)
            ?? throw new \LogicException('Claimed inventory key could not be reloaded.');
    }

    public function addIfMissing(SupplierProvider $provider, string $sku, string $code): bool
    {
        $inserted = $this->getEntityManager()->getConnection()->executeStatement(
            'INSERT INTO inventory_key (provider, sku, code) VALUES (:provider, :sku, :code) ON CONFLICT (code) DO NOTHING',
            ['provider' => $provider->value, 'sku' => $sku, 'code' => $code],
        );

        return 1 === $inserted;
    }
}
