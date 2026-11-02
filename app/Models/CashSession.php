<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashSession extends Model
{
    protected $guarded = [];
    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime', 'closed_at' => 'datetime',
            'opening_float' => 'decimal:2', 'theoretical_closing' => 'decimal:2',
            'actual_closing' => 'decimal:2', 'variance' => 'decimal:2',
        ];
    }
    public function register(): BelongsTo { return $this->belongsTo(CashRegister::class, 'cash_register_id'); }
    public function opener(): BelongsTo { return $this->belongsTo(User::class, 'opened_by'); }
    public function closer(): BelongsTo { return $this->belongsTo(User::class, 'closed_by'); }
    public function movements(): HasMany { return $this->hasMany(CashMovement::class); }
    public function isOpen(): bool { return $this->closed_at === null; }
}
