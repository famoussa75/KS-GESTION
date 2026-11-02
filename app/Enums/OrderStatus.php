<?php

namespace App\Enums;

enum OrderStatus: string
{
    case PENDING = 'pending';
    case VALIDATED = 'validated';
    case PREPARATION = 'preparation';
    case DELIVERED = 'delivered';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'En attente',
            self::VALIDATED => 'Validée',
            self::PREPARATION => 'En préparation',
            self::DELIVERED => 'Livrée',
            self::CANCELLED => 'Annulée',
        };
    }

    public function next(): ?self
    {
        return match ($this) {
            self::PENDING => self::VALIDATED,
            self::VALIDATED => self::PREPARATION,
            self::PREPARATION => self::DELIVERED,
            self::DELIVERED, self::CANCELLED => null,
        };
    }

    public function isCancellable(): bool
    {
        return in_array($this, [self::PENDING, self::VALIDATED, self::PREPARATION], true);
    }
}
