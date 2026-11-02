<?php

namespace App\Enums;

enum AlertType: string
{
    case LOW_STOCK = 'low_stock';
    case EXPIRY = 'expiry';
}
