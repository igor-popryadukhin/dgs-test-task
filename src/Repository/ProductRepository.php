<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Product> */
final class ProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    public function findActiveBySku(string $sku): ?Product
    {
        return $this->findOneBy(['sku' => $sku, 'active' => true]);
    }

    /** @return list<Product> */
    public function findAllActive(): array
    {
        return $this->findBy(['active' => true], ['priceMinor' => 'ASC']);
    }
}
