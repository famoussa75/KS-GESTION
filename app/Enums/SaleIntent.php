<?php

namespace App\Enums;

enum SaleIntent: string
{
    case QUOTE = 'quote';
    case DIRECT = 'direct';
    case ORDER = 'order';

    public function label(): string
    {
        return match ($this) {
            self::QUOTE => 'Devis',
            self::DIRECT => 'Vente directe',
            self::ORDER => 'Commande à livrer',
        };
    }
}
