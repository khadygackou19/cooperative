<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MouvementStockage extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['quantite' => 'float', 'date_mouvement' => 'date:Y-m-d'];
    }

    public function produit(): BelongsTo
    {
        return $this->belongsTo(Produit::class);
    }

    public function stock(): BelongsTo
    {
        return $this->belongsTo(Stock::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
