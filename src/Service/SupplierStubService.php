<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\SupplierProvider;
use App\Domain\SupplierResult;
use App\Entity\SupplierIssue;
use App\Exception\DomainProblem;
use App\Repository\AdvisoryLock;
use App\Repository\InventoryKeyRepository;
use App\Repository\PurchaseOrderRepository;
use App\Repository\SupplierIssueRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;

final readonly class SupplierStubService
{
    /**
     * @param array<string, float> $failureRates
     * @param array<string, float> $timeoutRates
     */
    public function __construct(
        private EntityManagerInterface $entityManager,
        private AdvisoryLock $advisoryLock,
        private InventoryKeyRepository $inventoryKeyRepository,
        private PurchaseOrderRepository $orderRepository,
        private SupplierIssueRepository $supplierIssueRepository,
        private array $failureRates,
        private array $timeoutRates,
        private float $timeoutSeconds,
    ) {
    }

    public function issue(
        SupplierProvider $provider,
        string $requestId,
        string $sku,
        string $orderId,
        ?string $forcedMode = null,
    ): SupplierResult {
        $operation = function () use ($provider, $requestId, $sku, $orderId, $forcedMode): SupplierResult {
            $this->advisoryLock->acquire('supplier:'.$requestId);
            $this->advisoryLock->acquire('supplier-order:'.$orderId);

            $existingIssue = $this->supplierIssueRepository->findByRequestId($requestId);
            if (null !== $existingIssue) {
                return SupplierResult::success($existingIssue->getCode());
            }

            $order = $this->orderRepository->find($orderId);
            if (null === $order) {
                throw new DomainProblem('Order was not found.', Response::HTTP_NOT_FOUND);
            }

            // A provider may have completed a request even when its response timed out.
            // Reusing the recorded issue makes an A -> B fallback safe.
            $existingOrderIssue = $this->supplierIssueRepository->findByOrder($order);
            if (null !== $existingOrderIssue) {
                return SupplierResult::success($existingOrderIssue->getCode());
            }

            if ('error' === $forcedMode || $this->hit($this->failureRates[$provider->value] ?? 0.0)) {
                return SupplierResult::error('provider_failure');
            }

            $inventoryKey = $this->inventoryKeyRepository->claimAvailable($provider, $sku);
            if (null === $inventoryKey) {
                return SupplierResult::error('out_of_stock');
            }

            $inventoryKey->assignTo($order);
            $this->entityManager->persist(new SupplierIssue(
                $requestId,
                $provider,
                $sku,
                $order,
                $inventoryKey,
                $inventoryKey->getCode(),
            ));

            // Flush before a simulated ambiguous timeout so a fallback in the same
            // transaction can observe and reuse the issue that already succeeded.
            $this->entityManager->flush();

            if ('timeout' === $forcedMode || $this->hit($this->timeoutRates[$provider->value] ?? 0.0)) {
                $this->waitForTimeout();

                return SupplierResult::timeout();
            }

            return SupplierResult::success($inventoryKey->getCode());
        };

        if ($this->entityManager->getConnection()->isTransactionActive()) {
            return $operation();
        }

        return $this->entityManager->wrapInTransaction($operation);
    }

    private function hit(float $rate): bool
    {
        return $rate > 0 && random_int(1, 10_000) <= (int) round(min(1.0, $rate) * 10_000);
    }

    private function waitForTimeout(): void
    {
        $microseconds = (int) round(max(0.0, $this->timeoutSeconds) * 1_000_000);
        if ($microseconds > 0) {
            usleep($microseconds);
        }
    }
}
