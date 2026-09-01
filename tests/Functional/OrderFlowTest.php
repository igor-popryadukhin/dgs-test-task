<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Domain\SupplierProvider;
use App\Service\DeliveryService;
use App\Service\OrderService;
use App\Service\PaymentInboxService;
use App\Service\SupplierStubService;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class OrderFlowTest extends KernelTestCase
{
    private Connection $connection;
    private OrderService $orders;
    private PaymentInboxService $payments;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $this->connection = $container->get(Connection::class);
        $this->orders = $container->get(OrderService::class);
        $this->payments = $container->get(PaymentInboxService::class);
        $this->resetState();
    }

    public function testDoubleClickCreatesOneOrderAndConsumesPromoOnce(): void
    {
        $requestId = Uuid::v7()->toRfc4122();
        $first = $this->orders->create('KEY-GTA5', $requestId, 'LIMIT3');
        $second = $this->orders->create('KEY-GTA5', $requestId, 'LIMIT3');

        self::assertSame($first->getId(), $second->getId());
        self::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM purchase_order'));
        self::assertSame(1, (int) $this->connection->fetchOne("SELECT used_count FROM promo_code WHERE code = 'LIMIT3'"));
    }

    public function testDuplicateWebhookDeliversExactlyOneKey(): void
    {
        $order = $this->orders->create('KEY-CS2-PRIME', Uuid::v7()->toRfc4122(), null);
        $payload = $this->paidPayload($order->getId(), $order->amount, 'same-event');

        for ($attempt = 0; $attempt < 50; ++$attempt) {
            $this->payments->receive($payload);
        }

        $delivered = $this->orders->get($order->getId());
        self::assertSame('delivered', $delivered->getStatus());
        self::assertNotNull($delivered->getIssuedCode());
        self::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM supplier_issue WHERE order_id = :id', ['id' => $order->getId()]));
        self::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM inventory_key WHERE assigned_order_id = :id', ['id' => $order->getId()]));
    }

    public function testWebhookBeforeOrderIsRetainedAndReconciled(): void
    {
        $orderId = Uuid::v7()->toRfc4122();
        $this->payments->receive($this->paidPayload($orderId, '1290.00', 'early-event'));
        self::assertFalse((bool) $this->connection->fetchOne("SELECT processed_at IS NOT NULL FROM payment_event WHERE event_id = 'early-event'"));

        $order = $this->orders->create('KEY-CS2-PRIME', $orderId, null);
        $this->payments->redispatchPending($order->getId());

        self::assertSame('delivered', $this->orders->get($order->getId())->getStatus());
    }

    public function testAmbiguousSupplierTimeoutIsIdempotent(): void
    {
        $order = $this->orders->create('KEY-EFT', Uuid::v7()->toRfc4122(), null);
        /** @var SupplierStubService $supplier */
        $supplier = self::getContainer()->get(SupplierStubService::class);
        $requestId = 'fixed-request-id';

        $timeout = $supplier->issue(SupplierProvider::A, $requestId, 'KEY-EFT', $order->getId(), 'timeout');
        $fallback = $supplier->issue(SupplierProvider::B, 'fallback-request-id', 'KEY-EFT', $order->getId());
        $retry = $supplier->issue(SupplierProvider::A, $requestId, 'KEY-EFT', $order->getId());

        self::assertSame('timeout', $timeout->status);
        self::assertTrue($fallback->isSuccess());
        self::assertTrue($retry->isSuccess());
        self::assertSame($fallback->code, $retry->code);
        self::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM supplier_issue WHERE request_id = :id', ['id' => $requestId]));
        self::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM supplier_issue WHERE order_id = :id', ['id' => $order->getId()]));
    }

    public function testOlderFailureAfterPaidEventDoesNotRegressDeliveredOrder(): void
    {
        $order = $this->orders->create('KEY-CS2-PRIME', Uuid::v7()->toRfc4122(), null);
        $paid = $this->paidPayload($order->getId(), $order->amount, 'newer-paid');
        $paid['created_at'] = '2026-08-31T12:01:00+03:00';
        $this->payments->receive($paid);

        $failed = $this->paidPayload($order->getId(), $order->amount, 'older-failed');
        $failed['status'] = 'failed';
        $failed['created_at'] = '2026-08-31T12:00:00+03:00';
        $this->payments->receive($failed);

        self::assertSame('delivered', $this->orders->get($order->getId())->getStatus());
        self::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM supplier_issue WHERE order_id = :id', ['id' => $order->getId()]));
    }

    public function testEmptyPoolCanBeRecoveredWithIdempotentRetry(): void
    {
        $sku = 'GIFT-XBOX-1500';
        $order = $this->orders->create($sku, Uuid::v7()->toRfc4122(), null);
        $this->connection->executeStatement('DELETE FROM inventory_key WHERE sku = :sku', ['sku' => $sku]);
        $this->payments->receive($this->paidPayload($order->getId(), $order->amount, 'stock-event'));
        self::assertSame('out_of_stock', $this->orders->get($order->getId())->getStatus());

        $this->connection->insert('inventory_key', ['provider' => 'A', 'sku' => $sku, 'code' => 'RECOVERY-CODE-001']);
        /** @var DeliveryService $delivery */
        $delivery = self::getContainer()->get(DeliveryService::class);
        $first = $delivery->deliver($order->getId());
        $second = $delivery->deliver($order->getId());

        self::assertSame('delivered', $first->getStatus());
        self::assertSame($first->getIssuedCode(), $second->getIssuedCode());
    }

    /** @return array<string, mixed> */
    private function paidPayload(string $orderId, string $amount, string $eventId): array
    {
        return ['event_id' => $eventId, 'order_id' => $orderId, 'status' => 'paid', 'amount' => $amount, 'currency' => 'RUB', 'created_at' => '2026-08-31T12:00:00+03:00'];
    }

    private function resetState(): void
    {
        $this->connection->executeStatement('DELETE FROM messenger_messages');
        $this->connection->executeStatement('DELETE FROM payment_event');
        $this->connection->executeStatement('DELETE FROM promo_redemption');
        $this->connection->executeStatement('DELETE FROM delivery_attempt');
        $this->connection->executeStatement('DELETE FROM supplier_issue');
        $this->connection->executeStatement('UPDATE inventory_key SET assigned_order_id = NULL, assigned_at = NULL');
        $this->connection->executeStatement('DELETE FROM purchase_order');
        $this->connection->executeStatement('UPDATE promo_code SET used_count = 0');
    }
}
