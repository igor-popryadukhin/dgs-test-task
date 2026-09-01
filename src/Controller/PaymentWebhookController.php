<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\PaymentInboxService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class PaymentWebhookController extends AbstractController
{
    public function __construct(private readonly PaymentInboxService $paymentInbox)
    {
    }

    #[Route('/api/webhooks/payment', name: 'api_payment_webhook', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        /** @var array<string, mixed> $paymentEvent */
        $paymentEvent = $request->toArray();

        return $this->json($this->paymentInbox->receive($paymentEvent));
    }
}
