<?php

namespace App\Enums;

enum UserRole: string
{
    case ADMIN = 'admin';
    case CASHIER = 'cashier';
    case STOCKKEEPER = 'stockkeeper';

    public function label(): string
    {
        return match ($this) {
            self::ADMIN => 'Accès total',
            self::CASHIER => 'Caissier',
            self::STOCKKEEPER => 'Magasinier',
        };
    }
}
