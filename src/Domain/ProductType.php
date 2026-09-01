<?php

declare(strict_types=1);

namespace App\Domain;

enum ProductType: string
{
    case TopUp = 'topup';
    case Key = 'key';
    case Subscription = 'subscription';
    case GiftCard = 'giftcard';
}
