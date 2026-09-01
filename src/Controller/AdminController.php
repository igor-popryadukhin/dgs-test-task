<?php

declare(strict_types=1);

namespace App\Controller;

use App\Exception\DomainProblem;
use App\Http\Request\InventoryKeysInput;
use App\Http\View\OrderView;
use App\Service\AdminTokenGuard;
use App\Service\DeliveryService;
use App\Service\InventoryService;
use App\Service\OrderService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AdminController extends AbstractController
{
    public function __construct(
        private readonly AdminTokenGuard $adminTokenGuard,
        private readonly DeliveryService $deliveryService,
        private readonly InventoryService $inventoryService,
        private readonly OrderService $orderService,
    ) {
    }

    #[Route('/admin', name: 'admin_orders', methods: ['GET'])]
    public function ordersPage(): Response
    {
        return $this->render('admin/index.html.twig', [
            'orders' => $this->orderService->recoverable(),
        ]);
    }

    #[Route('/api/admin/orders', name: 'api_admin_orders', methods: ['GET'])]
    public function listRecoverableOrders(Request $request): JsonResponse
    {
        $this->assertAdminAccess($request);

        return $this->json(['orders' => array_map(
            static fn (OrderView $order): array => $order->toArray(),
            $this->orderService->recoverable(),
        )]);
    }

    #[Route('/api/admin/orders/{orderId}/retry', name: 'api_admin_retry', methods: ['POST'])]
    public function retryDelivery(string $orderId, Request $request): JsonResponse
    {
        $this->assertAdminAccess($request);

        return $this->json($this->deliveryService->deliver($orderId)->toArray());
    }

    #[Route('/api/admin/inventory', name: 'api_admin_inventory_add', methods: ['POST'])]
    public function addInventoryKeys(Request $request): JsonResponse
    {
        $this->assertAdminAccess($request);
        $inventoryInput = InventoryKeysInput::fromRequest($request);
        $createdKeyCount = $this->inventoryService->addKeys(
            $inventoryInput->provider,
            $inventoryInput->sku,
            $inventoryInput->codes,
        );

        return $this->json(['created' => $createdKeyCount], Response::HTTP_CREATED);
    }

    private function assertAdminAccess(Request $request): void
    {
        if (!$this->adminTokenGuard->allows($request)) {
            throw new DomainProblem('Invalid admin token.', Response::HTTP_UNAUTHORIZED);
        }
    }
}
