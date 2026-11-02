<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoicePayment extends Model
{
    protected $guarded = [];
    protected function casts(): array { return ['amount' => 'decimal:2', 'paid_at' => 'datetime']; }
    public function invoice(): BelongsTo { return $this->belongsTo(Invoice::class); }
    public function cashMovement(): BelongsTo { return $this->belongsTo(CashMovement::class); }
}
