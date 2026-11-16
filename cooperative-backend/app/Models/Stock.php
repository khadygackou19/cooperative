<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Un Stock est un entrepôt physique. */
class Stock extends Model
{
    protected $guarded = [];

    public function mouvements(): HasMany
    {
        return $this->hasMany(MouvementStockage::class);
    }
}
