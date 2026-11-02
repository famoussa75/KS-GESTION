<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProformaLine extends Model
{
    protected $guarded = [];

    public function proforma(): BelongsTo { return $this->belongsTo(Proforma::class); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
}
