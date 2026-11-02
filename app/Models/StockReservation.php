<?php

namespace App\Models;

use App\Enums\ReservationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockReservation extends Model
{
    protected $guarded = [];
    protected function casts(): array { return ['status' => ReservationStatus::class, 'quantity' => 'decimal:3']; }
    public function orderLine(): BelongsTo { return $this->belongsTo(OrderLine::class); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function batch(): BelongsTo { return $this->belongsTo(StockBatch::class, 'stock_batch_id'); }
}
