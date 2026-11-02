<?php

namespace App\Enums;

enum SaleStatus: string
{
    case DRAFT = 'draft';
    case SENT = 'sent';
    case CONFIRMED = 'confirmed';
    case PREPARATION = 'preparation';
    case DELIVERED = 'delivered';
    case INVOICED = 'invoiced';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Brouillon',
            self::SENT => 'Envoyé',
            self::CONFIRMED => 'Confirmé',
            self::PREPARATION => 'En préparation',
            self::DELIVERED => 'Livré',
            self::INVOICED => 'Facturé',
            self::CANCELLED => 'Annulé',
        };
    }

    /**
     * Next MANUAL status step. Reaching "invoiced" is never a manual advance — it's an
     * automatic side-effect (direct: right after creation, order: right after delivery,
     * quote: only through explicit conversion) handled by SaleController/InvoiceService.
     */
    public function nextFor(SaleIntent $intent): ?self
    {
        return match ($intent) {
            SaleIntent::QUOTE => match ($this) {
                self::DRAFT => self::SENT,
                self::SENT => self::CONFIRMED,
                default => null,
            },
            SaleIntent::DIRECT => match ($this) {
                self::DRAFT => self::CONFIRMED,
                default => null,
            },
            SaleIntent::ORDER => match ($this) {
                self::DRAFT => self::CONFIRMED,
                self::CONFIRMED => self::PREPARATION,
                self::PREPARATION => self::DELIVERED,
                default => null,
            },
        };
    }
}
