<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\DeliveryAttemptStatus;
use App\Domain\OrderStatus;
use App\Domain\SupplierProvider;
use App\Domain\SupplierResult;
use App\Entity\DeliveryAttempt;
use App\Entity\PurchaseOrder;
use App\Exception\DomainProblem;
use App\Http\View\OrderView;
use App\Repository\AdvisoryLock;
use App\Repository\PurchaseOrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;

final readonly class DeliveryService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private AdvisoryLock $advisoryLock,
        private PurchaseOrderRepository $orderRepository,
        private SupplierStubService $supplierStub,
    ) {
    }

    public function deliver(string $orderId): OrderView
    {
        $order = $this->entityManager->wrapInTransaction(function () use ($orderId): PurchaseOrder {
            $this->advisoryLock->acquire('order:'.$orderId);
            $order = $this->orderRepository->findForUpdate($orderId);
            if (null === $order) {
                throw new DomainProblem('Order was not found.', Response::HTTP_NOT_FOUND);
            }

            return $this->deliverLocked($order);
        });

        return new OrderView($order, includeProduct: true, includeDelivery: true);
    }

    public function deliverLocked(PurchaseOrder $order): PurchaseOrder
    {
        if (OrderStatus::Delivered === $order->getStatus()) {
            return $order;
        }
        if (!in_array($order->getStatus(), [
            OrderStatus::Paid,
            OrderStatus::Delivering,
            OrderStatus::OutOfStock,
            OrderStatus::DeliveryFailed,
        ], true)) {
            return $order;
        }

        $order->markDelivering();
        $lastResult = SupplierResult::error('delivery_failed');
        $providerFailed = false;

        foreach (SupplierProvider::cases() as $provider) {
            $requestId = sprintf('issue-%s-%s', $order->getId(), strtolower($provider->value));
            $this->recordAttempt($order, $provider, $requestId, DeliveryAttemptStatus::Attempting);
            $supplierResult = $this->supplierStub->issue(
                $provider,
                $requestId,
                $order->getProduct()->getSku(),
                $order->getId(),
            );

            if ($supplierResult->isSuccess()) {
                $issuedCode = $supplierResult->code
                    ?? throw new \LogicException('A successful supplier result must contain a code.');
                $order->markDelivered($issuedCode);
                $this->recordAttempt($order, $provider, $requestId, DeliveryAttemptStatus::Delivered);

                return $order;
            }

            $lastResult = $supplierResult;
            $providerFailed = $providerFailed || 'out_of_stock' !== $supplierResult->reason;
            $this->recordAttempt(
                $order,
                $provider,
                $requestId,
                DeliveryAttemptStatus::Failed,
                $supplierResult->reason,
            );
        }

        $order->markDeliveryFailed(!$providerFailed && 'out_of_stock' === $lastResult->reason);

        return $order;
    }

    private function recordAttempt(
        PurchaseOrder $order,
        SupplierProvider $provider,
        string $requestId,
        DeliveryAttemptStatus $status,
        ?string $error = null,
    ): void {
        $deliveryAttempt = $order->getDeliveryAttempt();
        if (null === $deliveryAttempt) {
            $deliveryAttempt = new DeliveryAttempt($order, $requestId, $provider, $status);
            $this->entityManager->persist($deliveryAttempt);

            return;
        }

        $deliveryAttempt->record($requestId, $provider, $status, $error);
    }
}
