<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Product extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'sale_price' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'low_stock_threshold' => 'decimal:3',
            'active' => 'boolean',
        ];
    }

    public function subsidiary(): BelongsTo
    {
        return $this->belongsTo(Subsidiary::class);
    }

    public function stockItem(): HasOne
    {
        return $this->hasOne(StockItem::class);
    }

    public function batches(): HasMany
    {
        return $this->hasMany(StockBatch::class);
    }

    public function revenueAccount(): BelongsTo
    {
        return $this->belongsTo(AccountingAccount::class, 'revenue_account_id');
    }

    public function availableQuantity(): float
    {
        $stock = $this->stockItem;
        return $stock ? (float) $stock->quantity_on_hand - (float) $stock->reserved_quantity : 0.0;
    }
}
