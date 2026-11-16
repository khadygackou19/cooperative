<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un Produit est la production déclarée par un membre pour une culture.
 * Son statut est DÉRIVÉ : aucun mouvement d'entrée = en attente ; un mouvement d'entrée = validée.
 */
class Produit extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'quantite' => 'float',
            'prix_vente' => 'float',
            'date_entree' => 'date:Y-m-d',
        ];
    }

    public function culture(): BelongsTo
    {
        return $this->belongsTo(Culture::class);
    }

    public function mouvements(): HasMany
    {
        return $this->hasMany(MouvementStockage::class);
    }

    public function scopeValides(Builder $q): Builder
    {
        return $q->whereHas('mouvements', fn ($m) => $m->where('type_mouvement', 'entree'));
    }

    public function scopeEnAttente(Builder $q): Builder
    {
        return $q->whereDoesntHave('mouvements');
    }

    public function getStatutAttribute(): string
    {
        $mouvements = $this->relationLoaded('mouvements')
            ? $this->mouvements
            : $this->mouvements()->get();

        return $mouvements->contains('type_mouvement', 'entree') ? 'validee' : 'en_attente';
    }

    /** rupture | faible | normal (seulement pertinent pour une production validée) */
    public function getNiveauAttribute(): string
    {
        if ($this->quantite <= 0) {
            return 'rupture';
        }

        return $this->quantite < config('coop.seuil_stock_bas', 50) ? 'faible' : 'normal';
    }

    /** Entrepôt dans lequel la production a été reçue (premier mouvement d'entrée). */
    public function entrepot(): ?Stock
    {
        $mouvements = $this->relationLoaded('mouvements') ? $this->mouvements : $this->mouvements()->get();
        $entree = $mouvements->firstWhere('type_mouvement', 'entree');

        return $entree?->stock ?? ($entree ? Stock::find($entree->stock_id) : null);
    }
}
