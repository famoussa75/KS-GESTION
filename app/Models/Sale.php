<?php

namespace App\Models;

use App\Enums\SaleIntent;
use App\Enums\SaleStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Sale extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'intent' => SaleIntent::class,
            'status' => SaleStatus::class,
            'issue_date' => 'date',
            'valid_until' => 'date',
            'requested_delivery_date' => 'date',
            'subtotal' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'total' => 'decimal:2',
            'sent_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'validated_at' => 'datetime',
            'preparation_at' => 'datetime',
            'delivered_at' => 'datetime',
            'invoiced_at' => 'datetime',
        ];
    }

    public function subsidiary(): BelongsTo { return $this->belongsTo(Subsidiary::class); }
    public function client(): BelongsTo { return $this->belongsTo(Client::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function lines(): HasMany { return $this->hasMany(SaleLine::class); }
    public function invoice(): HasOne { return $this->hasOne(Invoice::class, 'source_sale_id'); }

    public function isEditable(): bool
    {
        return in_array($this->status, [SaleStatus::DRAFT, SaleStatus::SENT], true);
    }

    public function nextStatus(): ?SaleStatus
    {
        return $this->status->nextFor($this->intent);
    }
}
