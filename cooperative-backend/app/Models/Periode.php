<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Periode extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['date_debut' => 'date:Y-m-d'];
    }

    public function parcelle(): BelongsTo
    {
        return $this->belongsTo(Parcelle::class);
    }

    public function cultures(): HasMany
    {
        return $this->hasMany(Culture::class);
    }
}
