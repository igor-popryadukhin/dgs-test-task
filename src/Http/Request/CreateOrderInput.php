<?php

declare(strict_types=1);

namespace App\Http\Request;

use App\Exception\DomainProblem;
use Symfony\Component\HttpFoundation\Request;

final readonly class CreateOrderInput
{
    private function __construct(
        public string $sku,
        public string $clientRequestId,
        public ?string $promoCode,
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        /** @var array<string, mixed> $payload */
        $payload = $request->toArray();
        $sku = self::trimmedString($payload, 'sku');
        $clientRequestId = self::trimmedString($payload, 'client_request_id');

        if ('' === $sku || '' === $clientRequestId) {
            throw new DomainProblem('sku and client_request_id are required.');
        }

        $promoCode = self::trimmedString($payload, 'promo_code');

        return new self($sku, $clientRequestId, '' === $promoCode ? null : $promoCode);
    }

    /** @param array<string, mixed> $payload */
    private static function trimmedString(array $payload, string $field): string
    {
        return is_string($payload[$field] ?? null) ? trim($payload[$field]) : '';
    }
}
