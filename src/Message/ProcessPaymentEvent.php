<?php

declare(strict_types=1);

namespace App\Message;

final readonly class ProcessPaymentEvent
{
    public function __construct(public string $eventId)
    {
    }
}
