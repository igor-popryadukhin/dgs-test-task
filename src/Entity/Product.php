<?php

declare(strict_types=1);

namespace App\Entity;

use App\Domain\ProductType;
use App\Repository\ProductRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProductRepository::class)]
#[ORM\Table(name: 'product')]
class Product
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 64)]
        private string $sku,
        #[ORM\Column(length: 255)]
        private string $name,
        #[ORM\Column(length: 32, enumType: ProductType::class)]
        private ProductType $type,
        #[ORM\Column]
        private int $priceMinor,
        #[ORM\Column(length: 3, options: ['fixed' => true])]
        private string $currency,
        #[ORM\Column(length: 255)]
        private string $imagePath,
        #[ORM\Column(options: ['default' => true])]
        private bool $active = true,
    ) {
    }

    public function getSku(): string
    {
        return $this->sku;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): ProductType
    {
        return $this->type;
    }

    public function getPriceMinor(): int
    {
        return $this->priceMinor;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getImagePath(): string
    {
        return $this->imagePath;
    }

    public function isActive(): bool
    {
        return $this->active;
    }
}
