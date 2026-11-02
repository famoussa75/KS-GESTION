<?php

namespace App\Models;

use App\Enums\ProformaStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Proforma extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => ProformaStatus::class,
            'issue_date' => 'date',
            'valid_until' => 'date',
            'sent_at' => 'datetime',
            'converted_at' => 'datetime',
            'subtotal' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function subsidiary(): BelongsTo { return $this->belongsTo(Subsidiary::class); }
    public function client(): BelongsTo { return $this->belongsTo(Client::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function lines(): HasMany { return $this->hasMany(ProformaLine::class); }
    public function invoice(): HasOne { return $this->hasOne(Invoice::class, 'source_proforma_id'); }

    public function isEditable(): bool
    {
        return $this->status !== ProformaStatus::CONVERTED;
    }
}
