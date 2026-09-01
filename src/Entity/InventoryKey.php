<?php

declare(strict_types=1);

namespace App\Entity;

use App\Domain\SupplierProvider;
use App\Repository\InventoryKeyRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InventoryKeyRepository::class)]
#[ORM\Table(name: 'inventory_key')]
#[ORM\Index(name: 'inventory_available_idx', columns: ['provider', 'sku', 'id'], options: ['where' => '(assigned_order_id IS NULL)'])]
class InventoryKey
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::BIGINT)]
    private ?int $id;

    #[ORM\OneToOne(targetEntity: PurchaseOrder::class)]
    #[ORM\JoinColumn(name: 'assigned_order_id', referencedColumnName: 'id', nullable: true, unique: true)]
    private ?PurchaseOrder $assignedOrder = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $assignedAt = null;

    public function __construct(
        #[ORM\Column(length: 8, enumType: SupplierProvider::class)]
        private SupplierProvider $provider,
        #[ORM\ManyToOne(targetEntity: Product::class)]
        #[ORM\JoinColumn(name: 'sku', referencedColumnName: 'sku', nullable: false)]
        private Product $product,
        #[ORM\Column(length: 128, unique: true)]
        private string $code,
        ?int $id = null,
    ) {
        $this->id = $id;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProvider(): SupplierProvider
    {
        return $this->provider;
    }

    public function getProduct(): Product
    {
        return $this->product;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getAssignedOrder(): ?PurchaseOrder
    {
        return $this->assignedOrder;
    }

    public function getAssignedAt(): ?\DateTimeImmutable
    {
        return $this->assignedAt;
    }

    public function assignTo(PurchaseOrder $order): void
    {
        $this->assignedOrder = $order;
        $this->assignedAt = new \DateTimeImmutable();
    }
}
