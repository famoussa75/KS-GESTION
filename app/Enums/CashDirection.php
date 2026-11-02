<?php

namespace App\Enums;

enum CashDirection: string
{
    case IN = 'in';
    case OUT = 'out';

    public function label(): string
    {
        return $this === self::IN ? 'Entrée' : 'Sortie';
    }
}
