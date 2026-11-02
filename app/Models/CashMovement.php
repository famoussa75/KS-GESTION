<?php

namespace App\Models;

use App\Enums\CashDirection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CashMovement extends Model
{
    protected $guarded = [];
    protected function casts(): array
    {
        return ['direction' => CashDirection::class, 'amount' => 'decimal:2', 'occurred_at' => 'datetime'];
    }
    public function session(): BelongsTo { return $this->belongsTo(CashSession::class, 'cash_session_id'); }
    public function invoice(): BelongsTo { return $this->belongsTo(Invoice::class); }
    public function expenseAccount(): BelongsTo { return $this->belongsTo(AccountingAccount::class, 'expense_account_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function payment(): HasOne { return $this->hasOne(InvoicePayment::class); }
}
