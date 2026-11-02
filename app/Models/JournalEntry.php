<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class JournalEntry extends Model
{
    protected $guarded = [];
    protected function casts(): array { return ['entry_date' => 'date', 'posted' => 'boolean']; }
    public function subsidiary(): BelongsTo { return $this->belongsTo(Subsidiary::class); }
    public function journal(): BelongsTo { return $this->belongsTo(AccountingJournal::class, 'accounting_journal_id'); }
    public function lines(): HasMany { return $this->hasMany(JournalEntryLine::class); }
    public function source(): MorphTo { return $this->morphTo(); }
}
