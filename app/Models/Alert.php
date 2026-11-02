<?php

namespace App\Models;

use App\Enums\AlertType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Alert extends Model
{
    protected $guarded = [];
    protected function casts(): array { return ['type' => AlertType::class, 'active' => 'boolean', 'resolved_at' => 'datetime']; }
    public function subsidiary(): BelongsTo { return $this->belongsTo(Subsidiary::class); }
}
