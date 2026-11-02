<?php

namespace App\Enums;

enum ProformaStatus: string
{
    case DRAFT = 'draft';
    case SENT = 'sent';
    case CONVERTED = 'converted';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Brouillon',
            self::SENT => 'Envoyée',
            self::CONVERTED => 'Convertie',
        };
    }
}
