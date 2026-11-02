<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JournalEntryLine extends Model
{
    protected $guarded = [];
    protected function casts(): array { return ['debit' => 'decimal:2', 'credit' => 'decimal:2']; }
    public function entry(): BelongsTo { return $this->belongsTo(JournalEntry::class, 'journal_entry_id'); }
    public function account(): BelongsTo { return $this->belongsTo(AccountingAccount::class, 'accounting_account_id'); }
}
