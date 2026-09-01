<?php

declare(strict_types=1);

namespace App\Domain;

enum OrderStatus: string
{
    case Created = 'created';
    case Paid = 'paid';
    case Delivering = 'delivering';
    case Delivered = 'delivered';
    case PaymentFailed = 'payment_failed';
    case OutOfStock = 'out_of_stock';
    case DeliveryFailed = 'delivery_failed';

    public function isFinal(): bool
    {
        return self::Delivered === $this || self::PaymentFailed === $this;
    }

    public function isRecoverable(): bool
    {
        return self::OutOfStock === $this || self::DeliveryFailed === $this;
    }
}
