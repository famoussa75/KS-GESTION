<?php

namespace App\Models;

use LogicException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceLine extends Model
{
    protected $guarded = [];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Une ligne de facture définitive est immuable.'));
        static::deleting(fn () => throw new LogicException('Une ligne de facture définitive ne peut pas être supprimée.'));
    }

    public function invoice(): BelongsTo { return $this->belongsTo(Invoice::class); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function revenueAccount(): BelongsTo { return $this->belongsTo(AccountingAccount::class, 'revenue_account_id'); }
}
