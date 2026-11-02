<?php

namespace App\Models;

use App\Enums\StockMovementType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class StockMovement extends Model
{
    protected $guarded = [];
    protected function casts(): array
    {
        return ['type' => StockMovementType::class, 'occurred_at' => 'datetime', 'quantity' => 'decimal:3', 'quantity_before' => 'decimal:3', 'quantity_after' => 'decimal:3'];
    }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function batch(): BelongsTo { return $this->belongsTo(StockBatch::class, 'stock_batch_id'); }
    public function source(): MorphTo { return $this->morphTo(); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
