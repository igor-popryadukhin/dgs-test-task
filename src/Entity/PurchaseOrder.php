<?php

declare(strict_types=1);

namespace App\Entity;

use App\Domain\OrderStatus;
use App\Repository\PurchaseOrderRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PurchaseOrderRepository::class)]
#[ORM\Table(name: 'purchase_order')]
#[ORM\Index(name: 'purchase_order_status_idx', columns: ['status'])]
class PurchaseOrder
{
    #[ORM\OneToOne(mappedBy: 'order', targetEntity: DeliveryAttempt::class)]
    private ?DeliveryAttempt $deliveryAttempt = null;

    #[ORM\Column(length: 128, nullable: true)]
    private ?string $issuedCode = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $paymentEventAt = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, options: ['default' => 'CURRENT_TIMESTAMP'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, options: ['default' => 'CURRENT_TIMESTAMP'])]
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: Types::GUID)]
        private string $id,
        #[ORM\Column(type: Types::GUID, unique: true)]
        private string $clientRequestId,
        #[ORM\ManyToOne(targetEntity: Product::class)]
        #[ORM\JoinColumn(name: 'sku', referencedColumnName: 'sku', nullable: false)]
        private Product $product,
        #[ORM\Column(length: 32, enumType: OrderStatus::class)]
        private OrderStatus $status,
        #[ORM\Column]
        private int $amountMinor,
        #[ORM\Column]
        private int $originalAmountMinor,
        #[ORM\Column(length: 3, options: ['fixed' => true])]
        private string $currency,
        #[ORM\ManyToOne(targetEntity: PromoCode::class)]
        #[ORM\JoinColumn(name: 'promo_code', referencedColumnName: 'code', nullable: true)]
        private ?PromoCode $promoCode = null,
    ) {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getClientRequestId(): string
    {
        return $this->clientRequestId;
    }

    public function getProduct(): Product
    {
        return $this->product;
    }

    public function getStatus(): OrderStatus
    {
        return $this->status;
    }

    public function getAmountMinor(): int
    {
        return $this->amountMinor;
    }

    public function getOriginalAmountMinor(): int
    {
        return $this->originalAmountMinor;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getPromoCode(): ?PromoCode
    {
        return $this->promoCode;
    }

    public function getIssuedCode(): ?string
    {
        return $this->issuedCode;
    }

    public function getPaymentEventAt(): ?\DateTimeImmutable
    {
        return $this->paymentEventAt;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getDeliveryAttempt(): ?DeliveryAttempt
    {
        return $this->deliveryAttempt;
    }

    public function setDeliveryAttempt(DeliveryAttempt $deliveryAttempt): void
    {
        $this->deliveryAttempt = $deliveryAttempt;
    }

    public function markPaid(\DateTimeImmutable $occurredAt): void
    {
        $this->status = OrderStatus::Paid;
        $this->paymentEventAt = $occurredAt;
        $this->touch();
    }

    public function markPaymentFailed(\DateTimeImmutable $occurredAt): void
    {
        $this->status = OrderStatus::PaymentFailed;
        $this->paymentEventAt = $occurredAt;
        $this->touch();
    }

    public function markDelivering(): void
    {
        $this->status = OrderStatus::Delivering;
        $this->touch();
    }

    public function markDelivered(string $issuedCode): void
    {
        $this->status = OrderStatus::Delivered;
        $this->issuedCode = $issuedCode;
        $this->touch();
    }

    public function markDeliveryFailed(bool $allProvidersOutOfStock): void
    {
        $this->status = $allProvidersOutOfStock ? OrderStatus::OutOfStock : OrderStatus::DeliveryFailed;
        $this->touch();
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
