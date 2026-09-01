<?php

declare(strict_types=1);

namespace App\Domain;

enum PromoType: string
{
    case Percent = 'percent';
    case Amount = 'amount';
}
