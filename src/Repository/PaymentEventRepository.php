<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\PaymentEvent;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<PaymentEvent> */
final class PaymentEventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PaymentEvent::class);
    }

    public function registerIfNew(PaymentEvent $paymentEvent): bool
    {
        $inserted = $this->getEntityManager()->getConnection()->executeStatement(<<<'SQL'
            INSERT INTO payment_event (event_id, order_reference, status, amount_minor, currency, occurred_at, payload)
            VALUES (:event, :order, :status, :amount, :currency, :occurred, CAST(:payload AS JSONB))
            ON CONFLICT (event_id) DO NOTHING
        SQL, [
            'event' => $paymentEvent->getEventId(),
            'order' => $paymentEvent->getOrderReference(),
            'status' => $paymentEvent->getStatus()->value,
            'amount' => $paymentEvent->getAmountMinor(),
            'currency' => $paymentEvent->getCurrency(),
            'occurred' => $paymentEvent->getOccurredAt()->format('Y-m-d H:i:s.uP'),
            'payload' => json_encode($paymentEvent->getPayload(), JSON_THROW_ON_ERROR),
        ]);

        return 1 === $inserted;
    }

    public function findForUpdate(string $eventId): ?PaymentEvent
    {
        return $this->getEntityManager()->find(PaymentEvent::class, $eventId, LockMode::PESSIMISTIC_WRITE);
    }

    /** @return list<string> */
    public function findPendingIdsForOrder(string $orderId): array
    {
        /** @var list<array{eventId: string}> $rows */
        $rows = $this->createQueryBuilder('paymentEvent')
            ->select('paymentEvent.eventId')
            ->andWhere('paymentEvent.orderReference = :orderId')
            ->andWhere('paymentEvent.processedAt IS NULL')
            ->setParameter('orderId', $orderId)
            ->orderBy('paymentEvent.occurredAt', 'ASC')
            ->getQuery()
            ->getArrayResult();

        return array_column($rows, 'eventId');
    }
}
