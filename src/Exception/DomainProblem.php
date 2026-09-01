<?php

declare(strict_types=1);

namespace App\Exception;

final class DomainProblem extends \RuntimeException
{
    public function __construct(string $message, public readonly int $httpStatus = 422)
    {
        parent::__construct($message);
    }
}
