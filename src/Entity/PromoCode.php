<?php

declare(strict_types=1);

namespace App\Entity;

use App\Domain\PromoType;
use App\Repository\PromoCodeRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PromoCodeRepository::class)]
#[ORM\Table(name: 'promo_code')]
class PromoCode
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 64)]
        private string $code,
        #[ORM\Column(length: 16, enumType: PromoType::class)]
        private PromoType $type,
        #[ORM\Column]
        private int $value,
        #[ORM\Column(length: 3, nullable: true, options: ['fixed' => true])]
        private ?string $currency,
        #[ORM\Column]
        private int $maxUses,
        #[ORM\Column(options: ['default' => 0])]
        private int $usedCount = 0,
        #[ORM\Column(options: ['default' => true])]
        private bool $active = true,
    ) {
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getType(): PromoType
    {
        return $this->type;
    }

    public function getValue(): int
    {
        return $this->value;
    }

    public function getCurrency(): ?string
    {
        return $this->currency;
    }

    public function getMaxUses(): int
    {
        return $this->maxUses;
    }

    public function getUsedCount(): int
    {
        return $this->usedCount;
    }

    public function isActive(): bool
    {
        return $this->active;
    }
}
