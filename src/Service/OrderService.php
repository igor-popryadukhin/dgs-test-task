<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\OrderStatus;
use App\Domain\PromoType;
use App\Entity\Product;
use App\Entity\PromoRedemption;
use App\Entity\PurchaseOrder;
use App\Exception\DomainProblem;
use App\Http\View\OrderView;
use App\Repository\AdvisoryLock;
use App\Repository\ProductRepository;
use App\Repository\PromoCodeRepository;
use App\Repository\PurchaseOrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;

final readonly class OrderService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private AdvisoryLock $advisoryLock,
        private ProductRepository $productRepository,
        private PromoCodeRepository $promoCodeRepository,
        private PurchaseOrderRepository $orderRepository,
    ) {
    }

    public function create(string $sku, string $clientRequestId, ?string $promoCode): OrderView
    {
        if (!Uuid::isValid($clientRequestId)) {
            throw new DomainProblem('client_request_id must be a valid UUID.');
        }

        $normalizedPromoCode = null !== $promoCode && '' !== trim($promoCode)
            ? strtoupper(trim($promoCode))
            : null;

        $order = $this->entityManager->wrapInTransaction(function () use ($sku, $clientRequestId, $normalizedPromoCode): PurchaseOrder {
            $this->advisoryLock->acquire('order-create:'.$clientRequestId);

            $existingOrder = $this->orderRepository->findByClientRequestId($clientRequestId);
            if (null !== $existingOrder) {
                return $existingOrder;
            }

            $product = $this->productRepository->findActiveBySku($sku);
            if (null === $product) {
                throw new DomainProblem('Product is unavailable.', Response::HTTP_NOT_FOUND);
            }

            $originalAmount = $product->getPriceMinor();
            $amount = $originalAmount;
            $promotion = null;

            if (null !== $normalizedPromoCode) {
                $promotion = $this->promoCodeRepository->claimUsage($normalizedPromoCode);
                if (null === $promotion) {
                    throw new DomainProblem('Promo code is invalid or its usage limit has been reached.');
                }
                if (null !== $promotion->getCurrency() && $promotion->getCurrency() !== $product->getCurrency()) {
                    throw new DomainProblem('Promo code currency does not match the order currency.');
                }

                $discount = PromoType::Percent === $promotion->getType()
                    ? intdiv($originalAmount * $promotion->getValue(), 100)
                    : $promotion->getValue();
                $amount = max(0, $originalAmount - $discount);
            }

            // The client UUID doubles as the public order reference, allowing an
            // early payment webhook to be reconciled without another lookup key.
            $order = new PurchaseOrder(
                $clientRequestId,
                $clientRequestId,
                $product,
                OrderStatus::Created,
                $amount,
                $originalAmount,
                $product->getCurrency(),
                $promotion,
            );
            $this->entityManager->persist($order);

            if (null !== $promotion) {
                $this->entityManager->persist(new PromoRedemption($promotion, $order));
            }

            return $order;
        });

        return new OrderView($order);
    }

    public function get(string $orderId): OrderView
    {
        if (!Uuid::isValid($orderId)) {
            throw new DomainProblem('Order was not found.', Response::HTTP_NOT_FOUND);
        }

        $order = $this->orderRepository->find($orderId);
        if (null === $order) {
            throw new DomainProblem('Order was not found.', Response::HTTP_NOT_FOUND);
        }

        return new OrderView($order, includeProduct: true);
    }

    /** @return list<OrderView> */
    public function recoverable(): array
    {
        return array_map(
            static fn (PurchaseOrder $order): OrderView => new OrderView($order, includeProduct: true, includeDelivery: true),
            $this->orderRepository->findRecoverable(),
        );
    }

    /** @return list<Product> */
    public function products(): array
    {
        return $this->productRepository->findAllActive();
    }
}
