<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\OrderStatus;
use App\Domain\PaymentStatus;
use App\Repository\PaymentEventRepository;
use App\Repository\PurchaseOrderRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class PaymentEventProcessor
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PaymentEventRepository $paymentEventRepository,
        private PurchaseOrderRepository $orderRepository,
        private DeliveryService $deliveryService,
    ) {
    }

    public function process(string $eventId): void
    {
        $this->entityManager->wrapInTransaction(function () use ($eventId): void {
            $paymentEvent = $this->paymentEventRepository->findForUpdate($eventId);
            if (null === $paymentEvent || null !== $paymentEvent->getProcessedAt()) {
                return;
            }

            $order = $this->orderRepository->findForUpdate($paymentEvent->getOrderReference());
            if (null === $order) {
                return; // The durable inbox row stays pending until order creation reconciles it.
            }

            if ($paymentEvent->getAmountMinor() !== $order->getAmountMinor()
                || $paymentEvent->getCurrency() !== $order->getCurrency()) {
                $paymentEvent->markProcessed('amount_or_currency_mismatch');

                return;
            }

            if (null !== $order->getPaymentEventAt()
                && $paymentEvent->getOccurredAt() < $order->getPaymentEventAt()) {
                $paymentEvent->markProcessed();

                return;
            }

            if (OrderStatus::Delivered !== $order->getStatus()) {
                if (PaymentStatus::Failed === $paymentEvent->getStatus()) {
                    $order->markPaymentFailed($paymentEvent->getOccurredAt());
                } else {
                    $order->markPaid($paymentEvent->getOccurredAt());
                    $this->deliveryService->deliverLocked($order);
                }
            }

            $paymentEvent->markProcessed();
        });
    }
}
