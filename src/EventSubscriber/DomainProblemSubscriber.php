<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Exception\DomainProblem;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::EXCEPTION)]
final class DomainProblemSubscriber
{
    public function __invoke(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        if (!$exception instanceof DomainProblem) {
            return;
        }

        $event->setResponse(new JsonResponse([
            'error' => $exception->getMessage(),
            'status' => $exception->httpStatus,
        ], $exception->httpStatus));
    }
}
