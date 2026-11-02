<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashRegister extends Model
{
    protected $guarded = [];
    protected function casts(): array { return ['active' => 'boolean']; }
    public function subsidiary(): BelongsTo { return $this->belongsTo(Subsidiary::class); }
    public function sessions(): HasMany { return $this->hasMany(CashSession::class); }
}
