<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\OrderService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class StorefrontController extends AbstractController
{
    public function __construct(private readonly OrderService $orderService)
    {
    }

    #[Route('/', name: 'storefront', methods: ['GET'])]
    public function storefront(): Response
    {
        return $this->render('store/index.html.twig', [
            'products' => $this->orderService->products(),
        ]);
    }

    #[Route('/health', name: 'health', methods: ['GET'])]
    public function healthCheck(): JsonResponse
    {
        return $this->json(['status' => 'ok']);
    }

    #[Route('/orders/{orderId}', name: 'order_page', methods: ['GET'])]
    public function orderStatus(string $orderId): Response
    {
        return $this->render('order/show.html.twig', [
            'order' => $this->orderService->get($orderId),
        ]);
    }
}
