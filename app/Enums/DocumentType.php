<?php

namespace App\Enums;

enum DocumentType: string
{
    case INVOICE = 'invoice';
    case PROFORMA = 'proforma';
    case ORDER = 'order';
    case SALE = 'sale';
    case CASH_SESSION = 'cash_session';

    public function prefix(): string
    {
        return match ($this) {
            self::INVOICE => 'FAC',
            self::PROFORMA => 'PRO',
            self::ORDER => 'CMD',
            self::SALE => 'VTE',
            self::CASH_SESSION => 'CAI',
        };
    }
}
