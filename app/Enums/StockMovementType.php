<?php

namespace App\Enums;

enum StockMovementType: string
{
    case IN = 'in';
    case OUT = 'out';
    case RESERVE = 'reserve';
    case RELEASE = 'release';
    case ADJUSTMENT_IN = 'adjustment_in';
    case ADJUSTMENT_OUT = 'adjustment_out';

    public function label(): string
    {
        return match ($this) {
            self::IN => 'Entrée',
            self::OUT => 'Sortie',
            self::RESERVE => 'Réservation',
            self::RELEASE => 'Libération',
            self::ADJUSTMENT_IN => 'Ajustement +',
            self::ADJUSTMENT_OUT => 'Ajustement -',
        };
    }
}
