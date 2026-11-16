<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Commande extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['date_commande' => 'date:Y-m-d'];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function lignes(): HasMany
    {
        return $this->hasMany(LigneCommande::class);
    }

    public function livraisons(): HasMany
    {
        return $this->hasMany(Livraison::class);
    }

    /** Dernière livraison créée pour la commande. */
    public function livraison(): HasOne
    {
        return $this->hasOne(Livraison::class)->latestOfMany();
    }

    public function getMontantTotalAttribute(): float
    {
        $lignes = $this->relationLoaded('lignes') ? $this->lignes : $this->lignes()->get();

        return (float) $lignes->sum(fn ($l) => $l->quantite * $l->prix_unitaire);
    }
}
