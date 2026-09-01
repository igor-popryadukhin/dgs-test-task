<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\ProcessPaymentEvent;
use App\Service\PaymentEventProcessor;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ProcessPaymentEventHandler
{
    public function __construct(private PaymentEventProcessor $processor)
    {
    }

    public function __invoke(ProcessPaymentEvent $message): void
    {
        $this->processor->process($message->eventId);
    }
}
