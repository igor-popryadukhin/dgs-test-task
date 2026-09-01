<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\Request\CreateOrderInput;
use App\Http\Request\PaymentSimulationInput;
use App\Service\OrderService;
use App\Service\PaymentInboxService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/orders')]
final class OrderController extends AbstractController
{
    public function __construct(
        private readonly OrderService $orderService,
        private readonly PaymentInboxService $paymentInbox,
    ) {
    }

    #[Route('', name: 'api_order_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $orderInput = CreateOrderInput::fromRequest($request);
        $createdOrder = $this->orderService->create(
            $orderInput->sku,
            $orderInput->clientRequestId,
            $orderInput->promoCode,
        );
        $orderId = $createdOrder->getId();

        $this->paymentInbox->redispatchPending($orderId);
        $responseData = $createdOrder->toArray();
        $responseData['status_url'] = $this->generateUrl('api_order_get', ['orderId' => $orderId]);
        $responseData['payment_url'] = $this->generateUrl('api_order_simulate_payment', ['orderId' => $orderId]);

        return $this->json($responseData, Response::HTTP_CREATED);
    }

    #[Route('/{orderId}', name: 'api_order_get', methods: ['GET'])]
    public function getOrder(string $orderId): JsonResponse
    {
        return $this->json($this->orderService->get($orderId)->toArray());
    }

    #[Route('/{orderId}/simulate-payment', name: 'api_order_simulate_payment', methods: ['POST'])]
    public function simulatePayment(string $orderId, Request $request): JsonResponse
    {
        $order = $this->orderService->get($orderId);
        $paymentSimulation = PaymentSimulationInput::fromRequest($request);
        $webhookPayload = $paymentSimulation->toWebhookPayload($orderId, $order);

        return $this->json($this->paymentInbox->receive($webhookPayload), Response::HTTP_ACCEPTED);
    }
}
