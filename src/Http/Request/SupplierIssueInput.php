<?php

declare(strict_types=1);

namespace App\Http\Request;

use App\Exception\DomainProblem;
use Symfony\Component\HttpFoundation\Request;

final readonly class SupplierIssueInput
{
    private function __construct(
        public string $requestId,
        public string $sku,
        public string $orderId,
        public ?string $mode,
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        /** @var array<string, mixed> $payload */
        $payload = $request->toArray();

        return new self(
            self::requiredString($payload, 'request_id'),
            self::requiredString($payload, 'sku'),
            self::requiredString($payload, 'order_id'),
            self::optionalString($payload, 'mode'),
        );
    }

    /** @param array<string, mixed> $payload */
    private static function requiredString(array $payload, string $field): string
    {
        $value = self::optionalString($payload, $field);
        if (null === $value) {
            throw new DomainProblem(sprintf('%s is required.', $field));
        }

        return $value;
    }

    /** @param array<string, mixed> $payload */
    private static function optionalString(array $payload, string $field): ?string
    {
        if (!is_string($payload[$field] ?? null) || '' === trim($payload[$field])) {
            return null;
        }

        return trim($payload[$field]);
    }
}
