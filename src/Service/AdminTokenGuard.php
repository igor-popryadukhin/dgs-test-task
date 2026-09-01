<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\HttpFoundation\Request;

final readonly class AdminTokenGuard
{
    public function __construct(private string $token)
    {
    }

    public function allows(Request $request): bool
    {
        $provided = $request->headers->get('X-Admin-Token', '');

        return '' !== $provided && hash_equals($this->token, $provided);
    }
}
