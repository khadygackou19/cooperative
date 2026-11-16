<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Livraison extends Model
{
    protected $table = 'livraisons';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['date_prevue' => 'date:Y-m-d', 'date_livraison' => 'date:Y-m-d'];
    }

    public function commande(): BelongsTo
    {
        return $this->belongsTo(Commande::class);
    }

    public function vehicule(): BelongsTo
    {
        return $this->belongsTo(Vehicule::class);
    }

    public function chauffeur(): BelongsTo
    {
        return $this->belongsTo(Chauffeur::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
