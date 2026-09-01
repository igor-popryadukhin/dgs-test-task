<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\SupplierProvider;
use App\Exception\DomainProblem;
use App\Repository\InventoryKeyRepository;
use App\Repository\ProductRepository;
use Symfony\Component\HttpFoundation\Response;

final readonly class InventoryService
{
    public function __construct(
        private InventoryKeyRepository $inventoryKeyRepository,
        private ProductRepository $productRepository,
    ) {
    }

    /** @param non-empty-list<string> $codes */
    public function addKeys(SupplierProvider $provider, string $sku, array $codes): int
    {
        if (null === $this->productRepository->find($sku)) {
            throw new DomainProblem('Product was not found.', Response::HTTP_NOT_FOUND);
        }

        $createdKeyCount = 0;
        foreach ($codes as $code) {
            if ($this->inventoryKeyRepository->addIfMissing($provider, $sku, $code)) {
                ++$createdKeyCount;
            }
        }

        return $createdKeyCount;
    }
}
