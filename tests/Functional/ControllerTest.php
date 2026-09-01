<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;

final class ControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private Connection $connection;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->connection = self::getContainer()->get(Connection::class);
        $this->resetState();
    }

    public function testOrderCanBeCreatedPaidAndFetchedThroughHttpApi(): void
    {
        $clientRequestId = Uuid::v7()->toRfc4122();
        $this->client->jsonRequest('POST', '/api/orders', [
            'sku' => 'KEY-CS2-PRIME',
            'client_request_id' => $clientRequestId,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $createdOrder = $this->responseData();
        self::assertSame($clientRequestId, $createdOrder['id']);
        self::assertSame('/api/orders/'.$clientRequestId, $createdOrder['status_url']);
        self::assertSame('/api/orders/'.$clientRequestId.'/simulate-payment', $createdOrder['payment_url']);

        $this->client->jsonRequest('POST', (string) $createdOrder['payment_url']);
        self::assertResponseStatusCodeSame(Response::HTTP_ACCEPTED);

        $this->client->request('GET', (string) $createdOrder['status_url']);
        self::assertResponseIsSuccessful();
        self::assertSame('delivered', $this->responseData()['status']);
    }

    public function testInventoryEndpointRequiresAdminTokenAndHandlesDuplicateCodes(): void
    {
        $inventoryCode = 'HTTP-TEST-'.Uuid::v7()->toBase58();
        $payload = [
            'provider' => 'A',
            'sku' => 'KEY-CS2-PRIME',
            'codes' => [$inventoryCode],
        ];

        $this->client->jsonRequest('POST', '/api/admin/inventory', $payload);
        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);

        $adminToken = getenv('ADMIN_TOKEN');
        self::assertIsString($adminToken);
        $server = ['HTTP_X_ADMIN_TOKEN' => $adminToken];

        try {
            $this->client->jsonRequest('POST', '/api/admin/inventory', $payload, $server);
            self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
            self::assertSame(1, $this->responseData()['created']);

            $this->client->jsonRequest('POST', '/api/admin/inventory', $payload, $server);
            self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
            self::assertSame(0, $this->responseData()['created']);
        } finally {
            $this->connection->delete('inventory_key', ['code' => $inventoryCode]);
        }
    }

    public function testSupplierEndpointReturnsItsPublicContract(): void
    {
        $orderId = Uuid::v7()->toRfc4122();
        $requestId = 'http-supplier-'.Uuid::v7()->toBase58();
        $this->connection->insert('purchase_order', [
            'id' => $orderId,
            'client_request_id' => $orderId,
            'sku' => 'KEY-CS2-PRIME',
            'status' => 'paid',
            'amount_minor' => 129000,
            'original_amount_minor' => 129000,
            'currency' => 'RUB',
        ]);

        $this->client->jsonRequest('POST', '/api/suppliers/A/issue', [
            'request_id' => $requestId,
            'sku' => 'KEY-CS2-PRIME',
            'order_id' => $orderId,
        ]);

        self::assertResponseIsSuccessful();
        $supplierResponse = $this->responseData();
        self::assertSame('ok', $supplierResponse['status']);
        self::assertSame($requestId, $supplierResponse['request_id']);
        self::assertIsString($supplierResponse['code']);
        self::assertNotSame('', $supplierResponse['code']);
    }

    /** @return array<string, mixed> */
    private function responseData(): array
    {
        $responseContent = $this->client->getResponse()->getContent();
        if (false === $responseContent) {
            throw new \LogicException('The HTTP response content could not be read.');
        }

        /** @var array<string, mixed> $responseData */
        $responseData = json_decode($responseContent, true, flags: JSON_THROW_ON_ERROR);

        return $responseData;
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
