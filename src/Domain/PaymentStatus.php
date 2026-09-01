<?php

declare(strict_types=1);

namespace App\Domain;

enum PaymentStatus: string
{
    case Paid = 'paid';
    case Failed = 'failed';
}
