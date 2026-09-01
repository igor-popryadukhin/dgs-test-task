<?php

declare(strict_types=1);

namespace App\Http\Request;

use App\Http\View\OrderView;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Uid\Uuid;

final readonly class PaymentSimulationInput
{
    private function __construct(
        public string $eventId,
        public string $status,
        public string $occurredAt,
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        /** @var array<string, mixed> $payload */
        $payload = '' === trim($request->getContent()) ? [] : $request->toArray();

        return new self(
            self::stringOrDefault($payload, 'event_id', 'evt_'.Uuid::v7()->toBase58()),
            self::stringOrDefault($payload, 'status', 'paid'),
            self::stringOrDefault($payload, 'created_at', (new \DateTimeImmutable())->format(DATE_ATOM)),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toWebhookPayload(string $orderId, OrderView $order): array
    {
        return [
            'event_id' => $this->eventId,
            'order_id' => $orderId,
            'status' => $this->status,
            'amount' => $order->amount,
            'currency' => $order->getCurrency(),
            'created_at' => $this->occurredAt,
        ];
    }

    /** @param array<string, mixed> $payload */
    private static function stringOrDefault(array $payload, string $field, string $default): string
    {
        return is_string($payload[$field] ?? null) ? trim($payload[$field]) : $default;
    }
}
