<?php

declare(strict_types=1);

namespace App\Entity;

use App\Domain\DeliveryAttemptStatus;
use App\Domain\SupplierProvider;
use App\Repository\DeliveryAttemptRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: DeliveryAttemptRepository::class)]
#[ORM\Table(name: 'delivery_attempt')]
class DeliveryAttempt
{
    #[ORM\Id]
    #[ORM\Column(type: Types::GUID)]
    private string $id;

    #[ORM\Column(options: ['default' => 0])]
    private int $attempts = 0;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $lastError = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $nextRetryAt = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, options: ['default' => 'CURRENT_TIMESTAMP'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, options: ['default' => 'CURRENT_TIMESTAMP'])]
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        #[ORM\OneToOne(inversedBy: 'deliveryAttempt', targetEntity: PurchaseOrder::class)]
        #[ORM\JoinColumn(name: 'order_id', referencedColumnName: 'id', nullable: false, unique: true)]
        private PurchaseOrder $order,
        #[ORM\Column(length: 160)]
        private string $requestId,
        #[ORM\Column(length: 8, enumType: SupplierProvider::class)]
        private SupplierProvider $provider,
        #[ORM\Column(length: 32, enumType: DeliveryAttemptStatus::class)]
        private DeliveryAttemptStatus $status,
    ) {
        $this->id = Uuid::v7()->toRfc4122();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
        $this->record($requestId, $provider, $status);
        $order->setDeliveryAttempt($this);
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getOrder(): PurchaseOrder
    {
        return $this->order;
    }

    public function getRequestId(): string
    {
        return $this->requestId;
    }

    public function getProvider(): SupplierProvider
    {
        return $this->provider;
    }

    public function getStatus(): DeliveryAttemptStatus
    {
        return $this->status;
    }

    public function getAttempts(): int
    {
        return $this->attempts;
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    public function getNextRetryAt(): ?\DateTimeImmutable
    {
        return $this->nextRetryAt;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function record(
        string $requestId,
        SupplierProvider $provider,
        DeliveryAttemptStatus $status,
        ?string $lastError = null,
    ): void {
        $this->requestId = $requestId;
        $this->provider = $provider;
        $this->status = $status;
        ++$this->attempts;
        $this->lastError = $lastError;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function scheduleRetry(?\DateTimeImmutable $nextRetryAt): void
    {
        $this->nextRetryAt = $nextRetryAt;
        $this->updatedAt = new \DateTimeImmutable();
    }
}
