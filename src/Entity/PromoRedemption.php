<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PromoRedemptionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PromoRedemptionRepository::class)]
#[ORM\Table(name: 'promo_redemption')]
class PromoRedemption
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::BIGINT)]
    private ?int $id;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, options: ['default' => 'CURRENT_TIMESTAMP'])]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: PromoCode::class)]
        #[ORM\JoinColumn(name: 'promo_code', referencedColumnName: 'code', nullable: false)]
        private PromoCode $promoCode,
        #[ORM\OneToOne(targetEntity: PurchaseOrder::class)]
        #[ORM\JoinColumn(name: 'order_id', referencedColumnName: 'id', nullable: false, unique: true)]
        private PurchaseOrder $order,
        ?int $id = null,
    ) {
        $this->id = $id;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPromoCode(): PromoCode
    {
        return $this->promoCode;
    }

    public function getOrder(): PurchaseOrder
    {
        return $this->order;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
