<?php

declare(strict_types=1);

namespace App\Entity;

use App\Domain\PaymentStatus;
use App\Repository\PaymentEventRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PaymentEventRepository::class)]
#[ORM\Table(name: 'payment_event')]
#[ORM\Index(name: 'payment_event_pending_idx', columns: ['order_reference', 'occurred_at'], options: ['where' => '(processed_at IS NULL)'])]
class PaymentEvent
{
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $processingError = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $processedAt = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, options: ['default' => 'CURRENT_TIMESTAMP'])]
    private \DateTimeImmutable $receivedAt;

    /** @param array<string, mixed> $payload */
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 128)]
        private string $eventId,
        #[ORM\Column(type: Types::GUID)]
        private string $orderReference,
        #[ORM\Column(length: 16, enumType: PaymentStatus::class)]
        private PaymentStatus $status,
        #[ORM\Column]
        private int $amountMinor,
        #[ORM\Column(length: 3, options: ['fixed' => true])]
        private string $currency,
        #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
        private \DateTimeImmutable $occurredAt,
        #[ORM\Column(type: Types::JSONB)]
        private array $payload,
    ) {
        $this->receivedAt = new \DateTimeImmutable();
    }

    public function getEventId(): string
    {
        return $this->eventId;
    }

    public function getOrderReference(): string
    {
        return $this->orderReference;
    }

    public function getStatus(): PaymentStatus
    {
        return $this->status;
    }

    public function getAmountMinor(): int
    {
        return $this->amountMinor;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getOccurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    /** @return array<string, mixed> */
    public function getPayload(): array
    {
        return $this->payload;
    }

    public function getProcessingError(): ?string
    {
        return $this->processingError;
    }

    public function getProcessedAt(): ?\DateTimeImmutable
    {
        return $this->processedAt;
    }

    public function getReceivedAt(): \DateTimeImmutable
    {
        return $this->receivedAt;
    }

    public function markProcessed(?string $processingError = null): void
    {
        $this->processingError = $processingError;
        $this->processedAt = new \DateTimeImmutable();
    }
}
