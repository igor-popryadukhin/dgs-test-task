<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\SupplierProvider;
use App\Domain\SupplierResult;
use App\Http\Request\SupplierIssueInput;
use App\Service\SupplierStubService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SupplierController extends AbstractController
{
    public function __construct(private readonly SupplierStubService $supplierStub)
    {
    }

    #[Route('/api/suppliers/{provider}/issue', name: 'api_supplier_issue', requirements: ['provider' => 'A|B'], methods: ['POST'])]
    public function __invoke(string $provider, Request $request): JsonResponse
    {
        $issueInput = SupplierIssueInput::fromRequest($request);
        $supplierResult = $this->supplierStub->issue(
            SupplierProvider::from($provider),
            $issueInput->requestId,
            $issueInput->sku,
            $issueInput->orderId,
            $issueInput->mode,
        );

        return $this->json(
            $this->buildResponsePayload($supplierResult, $issueInput->requestId),
            $this->resolveHttpStatus($supplierResult),
        );
    }

    private function resolveHttpStatus(SupplierResult $supplierResult): int
    {
        return match ($supplierResult->status) {
            'ok' => Response::HTTP_OK,
            'timeout' => Response::HTTP_GATEWAY_TIMEOUT,
            default => 'out_of_stock' === $supplierResult->reason
                ? Response::HTTP_CONFLICT
                : Response::HTTP_SERVICE_UNAVAILABLE,
        };
    }

    /** @return array<string, string> */
    private function buildResponsePayload(SupplierResult $supplierResult, string $requestId): array
    {
        $responsePayload = [
            'status' => $supplierResult->status,
            'request_id' => $requestId,
        ];
        if (null !== $supplierResult->code) {
            $responsePayload['code'] = $supplierResult->code;
        }
        if (null !== $supplierResult->reason) {
            $responsePayload['reason'] = $supplierResult->reason;
        }

        return $responsePayload;
    }
}
