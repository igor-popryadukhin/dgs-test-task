<?php

declare(strict_types=1);

namespace App\Http\Request;

use App\Domain\SupplierProvider;
use App\Exception\DomainProblem;
use Symfony\Component\HttpFoundation\Request;

final readonly class InventoryKeysInput
{
    /** @param non-empty-list<string> $codes */
    private function __construct(
        public SupplierProvider $provider,
        public string $sku,
        public array $codes,
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        /** @var array<string, mixed> $payload */
        $payload = $request->toArray();
        $provider = is_string($payload['provider'] ?? null)
            ? SupplierProvider::tryFrom(trim($payload['provider']))
            : null;
        $sku = is_string($payload['sku'] ?? null) ? trim($payload['sku']) : '';
        $rawCodes = is_array($payload['codes'] ?? null) ? $payload['codes'] : [];
        $codes = [];

        foreach ($rawCodes as $code) {
            if (is_string($code) && '' !== trim($code)) {
                $codes[] = trim($code);
            }
        }

        if (null === $provider || '' === $sku || [] === $codes) {
            throw new DomainProblem('provider (A or B), sku and a non-empty codes array are required.');
        }

        return new self($provider, $sku, $codes);
    }
}
