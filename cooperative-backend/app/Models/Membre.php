<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Membre extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['date_adhesion' => 'date:Y-m-d'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parcelles(): HasMany
    {
        return $this->hasMany(Parcelle::class);
    }

    public function collectes(): HasMany
    {
        return $this->hasMany(Collecte::class);
    }
}
