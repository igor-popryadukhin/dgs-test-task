<?php

declare(strict_types=1);

namespace App\Domain;

enum DeliveryAttemptStatus: string
{
    case Attempting = 'attempting';
    case Delivered = 'delivered';
    case Failed = 'failed';
}
