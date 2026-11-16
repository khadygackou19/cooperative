<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Parcelle extends Model
{
    protected $guarded = [];

    public function membre(): BelongsTo
    {
        return $this->belongsTo(Membre::class);
    }

    public function periodes(): HasMany
    {
        return $this->hasMany(Periode::class);
    }
}
