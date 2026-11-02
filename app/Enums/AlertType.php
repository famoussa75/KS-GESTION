<?php

namespace App\Enums;

enum AlertType: string
{
    case LOW_STOCK = 'low_stock';
    case EXPIRY = 'expiry';

    public function label(): string
    {
        return match ($this) {
            self::LOW_STOCK => 'Stock faible',
            self::EXPIRY => 'Péremption',
        };
    }
}
