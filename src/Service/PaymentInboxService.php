<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\PaymentStatus;
use App\Entity\PaymentEvent;
use App\Exception\DomainProblem;
use App\Message\ProcessPaymentEvent;
use App\Repository\PaymentEventRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

final readonly class PaymentInboxService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PaymentEventRepository $paymentEventRepository,
        private MessageBusInterface $messageBus,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array{accepted: bool, duplicate: bool, event_id: string}
     */
    public function receive(array $payload): array
    {
        $eventId = $this->requiredString($payload, 'event_id');
        $orderId = $this->requiredString($payload, 'order_id');
        $paymentStatus = PaymentStatus::tryFrom($this->requiredString($payload, 'status'));
        $currency = strtoupper($this->requiredString($payload, 'currency'));

        if (!Uuid::isValid($orderId) || null === $paymentStatus) {
            throw new DomainProblem('Invalid webhook order_id or status.');
        }
        if (!isset($payload['amount']) || !is_numeric($payload['amount'])) {
            throw new DomainProblem('Webhook amount is required.');
        }

        try {
            $occurredAt = new \DateTimeImmutable($this->requiredString($payload, 'created_at'));
        } catch (\Throwable) {
            throw new DomainProblem('Webhook created_at must be an ISO-8601 timestamp.');
        }

        $paymentEvent = new PaymentEvent(
            $eventId,
            $orderId,
            $paymentStatus,
            (int) round((float) $payload['amount'] * 100),
            $currency,
            $occurredAt,
            $payload,
        );
        $inserted = $this->entityManager->wrapInTransaction(function () use ($paymentEvent, $eventId): bool {
            $inserted = $this->paymentEventRepository->registerIfNew($paymentEvent);
            if ($inserted) {
                $this->messageBus->dispatch(new ProcessPaymentEvent($eventId));
            }

            return $inserted;
        });

        return ['accepted' => true, 'duplicate' => !$inserted, 'event_id' => $eventId];
    }

    public function redispatchPending(string $orderId): void
    {
        foreach ($this->paymentEventRepository->findPendingIdsForOrder($orderId) as $eventId) {
            $this->messageBus->dispatch(new ProcessPaymentEvent($eventId));
        }
    }

    /** @param array<string, mixed> $payload */
    private function requiredString(array $payload, string $field): string
    {
        if (!isset($payload[$field]) || !is_string($payload[$field]) || '' === trim($payload[$field])) {
            throw new DomainProblem(sprintf('Webhook field "%s" is required.', $field));
        }

        return trim($payload[$field]);
    }
}
