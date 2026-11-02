<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subsidiary extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'tracks_expiry' => 'boolean',
            'active' => 'boolean',
        ];
    }

    public function groupProfile(): BelongsTo
    {
        return $this->belongsTo(GroupProfile::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function cashRegisters(): HasMany
    {
        return $this->hasMany(CashRegister::class);
    }

    public function effectiveLogoUrl(): string
    {
        if ($this->logo_path) {
            return asset('storage/'.$this->logo_path);
        }

        return asset($this->groupProfile?->logo_path ?: 'images/ks-logo.png');
    }
}
