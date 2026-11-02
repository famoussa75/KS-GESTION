<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GroupProfile extends Model
{
    protected $guarded = [];

    public function subsidiaries(): HasMany
    {
        return $this->hasMany(Subsidiary::class);
    }
}
