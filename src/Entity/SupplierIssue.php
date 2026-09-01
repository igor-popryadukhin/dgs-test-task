<?php

declare(strict_types=1);

namespace App\Entity;

use App\Domain\SupplierProvider;
use App\Repository\SupplierIssueRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SupplierIssueRepository::class)]
#[ORM\Table(name: 'supplier_issue')]
class SupplierIssue
{
    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, options: ['default' => 'CURRENT_TIMESTAMP'])]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 160)]
        private string $requestId,
        #[ORM\Column(length: 8, enumType: SupplierProvider::class)]
        private SupplierProvider $provider,
        #[ORM\Column(length: 64)]
        private string $sku,
        #[ORM\OneToOne(targetEntity: PurchaseOrder::class)]
        #[ORM\JoinColumn(name: 'order_id', referencedColumnName: 'id', nullable: false, unique: true)]
        private PurchaseOrder $order,
        #[ORM\OneToOne(targetEntity: InventoryKey::class)]
        #[ORM\JoinColumn(name: 'inventory_key_id', referencedColumnName: 'id', nullable: false, unique: true)]
        private InventoryKey $inventoryKey,
        #[ORM\Column(length: 128)]
        private string $code,
    ) {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getRequestId(): string
    {
        return $this->requestId;
    }

    public function getProvider(): SupplierProvider
    {
        return $this->provider;
    }

    public function getSku(): string
    {
        return $this->sku;
    }

    public function getOrder(): PurchaseOrder
    {
        return $this->order;
    }

    public function getInventoryKey(): InventoryKey
    {
        return $this->inventoryKey;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
