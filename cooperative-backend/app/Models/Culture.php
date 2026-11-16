<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Culture extends Model
{
    protected $guarded = [];

    public function periode(): BelongsTo
    {
        return $this->belongsTo(Periode::class);
    }

    public function produits(): HasMany
    {
        return $this->hasMany(Produit::class);
    }
}
