<?php

declare(strict_types=1);

namespace App\Domain;

final readonly class SupplierResult
{
    private function __construct(
        public string $status,
        public ?string $code = null,
        public ?string $reason = null,
    ) {
    }

    public static function success(string $code): self
    {
        return new self('ok', $code);
    }

    public static function error(string $reason): self
    {
        return new self('error', reason: $reason);
    }

    public static function timeout(): self
    {
        return new self('timeout', reason: 'ambiguous_timeout');
    }

    public function isSuccess(): bool
    {
        return 'ok' === $this->status;
    }
}
