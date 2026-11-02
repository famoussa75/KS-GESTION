<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $guarded = [];
    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'order_date' => 'date', 'requested_delivery_date' => 'date',
            'validated_at' => 'datetime', 'preparation_at' => 'datetime', 'delivered_at' => 'datetime', 'cancelled_at' => 'datetime',
        ];
    }
    public function subsidiary(): BelongsTo { return $this->belongsTo(Subsidiary::class); }
    public function client(): BelongsTo { return $this->belongsTo(Client::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function lines(): HasMany { return $this->hasMany(OrderLine::class); }
}
