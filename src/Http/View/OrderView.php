<?php

declare(strict_types=1);

namespace App\Http\View;

use App\Entity\PurchaseOrder;

final readonly class OrderView
{
    public string $amount;
    public string $originalAmount;
    public bool $isFinal;
    public bool $isRecoverable;
    public ?string $productName;
    public ?string $imagePath;
    public ?string $lastError;
    public ?int $attempts;

    public function __construct(private PurchaseOrder $order, bool $includeProduct = false, bool $includeDelivery = false)
    {
        $this->amount = number_format($order->getAmountMinor() / 100, 2, '.', '');
        $this->originalAmount = number_format($order->getOriginalAmountMinor() / 100, 2, '.', '');
        $this->isFinal = $order->getStatus()->isFinal();
        $this->isRecoverable = $order->getStatus()->isRecoverable();
        $this->productName = $includeProduct ? $order->getProduct()->getName() : null;
        $this->imagePath = $includeProduct ? $order->getProduct()->getImagePath() : null;
        $this->lastError = $includeDelivery ? $order->getDeliveryAttempt()?->getLastError() : null;
        $this->attempts = $includeDelivery ? $order->getDeliveryAttempt()?->getAttempts() : null;
    }

    public function getId(): string
    {
        return $this->order->getId();
    }

    public function getStatus(): string
    {
        return $this->order->getStatus()->value;
    }

    public function getCurrency(): string
    {
        return $this->order->getCurrency();
    }

    public function getIssuedCode(): ?string
    {
        return $this->order->getIssuedCode();
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $data = [
            'id' => $this->order->getId(),
            'client_request_id' => $this->order->getClientRequestId(),
            'sku' => $this->order->getProduct()->getSku(),
            'status' => $this->order->getStatus()->value,
            'amount_minor' => $this->order->getAmountMinor(),
            'original_amount_minor' => $this->order->getOriginalAmountMinor(),
            'currency' => $this->order->getCurrency(),
            'promo_code' => $this->order->getPromoCode()?->getCode(),
            'payment_event_at' => $this->formatDate($this->order->getPaymentEventAt()),
            'created_at' => $this->formatDate($this->order->getCreatedAt()),
            'updated_at' => $this->formatDate($this->order->getUpdatedAt()),
            'amount' => $this->amount,
            'original_amount' => $this->originalAmount,
            'is_final' => $this->isFinal,
            'is_recoverable' => $this->isRecoverable,
        ];

        if (null !== $this->productName) {
            $data['product_name'] = $this->productName;
            $data['image_path'] = $this->imagePath;
        }
        if (null !== $this->lastError || null !== $this->attempts) {
            $data['last_error'] = $this->lastError;
            $data['attempts'] = $this->attempts;
        }
        if (null !== $this->getIssuedCode() && 'delivered' === $this->getStatus()) {
            $data['issued_code'] = $this->getIssuedCode();
        }

        return $data;
    }

    private function formatDate(?\DateTimeImmutable $date): ?string
    {
        return $date?->format(DATE_ATOM);
    }
}
