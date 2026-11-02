<?php

namespace App\Models;

use LogicException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'due_date' => 'date',
            'subtotal' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Une facture définitive ne peut pas être modifiée.'));
        static::deleting(fn () => throw new LogicException('Une facture définitive ne peut pas être supprimée.'));
    }

    public function subsidiary(): BelongsTo { return $this->belongsTo(Subsidiary::class); }
    public function client(): BelongsTo { return $this->belongsTo(Client::class); }
    public function sourceProforma(): BelongsTo { return $this->belongsTo(Proforma::class, 'source_proforma_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function lines(): HasMany { return $this->hasMany(InvoiceLine::class); }
    public function payments(): HasMany { return $this->hasMany(InvoicePayment::class); }

    public function paidTotal(): float
    {
        return $this->relationLoaded('payments')
            ? (float) $this->payments->sum('amount')
            : (float) $this->payments()->sum('amount');
    }

    public function outstanding(): float
    {
        return max(0, (float) $this->total - $this->paidTotal());
    }
}
