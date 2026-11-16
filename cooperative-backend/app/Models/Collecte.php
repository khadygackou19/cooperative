<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Collecte extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'date_prevue' => 'date:Y-m-d',
            'quantite_prevue' => 'float',
            'quantite_collectee' => 'float',
        ];
    }

    public function membre(): BelongsTo
    {
        return $this->belongsTo(Membre::class);
    }

    public function produit(): BelongsTo
    {
        return $this->belongsTo(Produit::class);
    }

    public function stock(): BelongsTo
    {
        return $this->belongsTo(Stock::class);
    }

    public function vehicule(): BelongsTo
    {
        return $this->belongsTo(Vehicule::class);
    }

    public function chauffeur(): BelongsTo
    {
        return $this->belongsTo(Chauffeur::class);
    }
}
