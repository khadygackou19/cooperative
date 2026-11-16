<?php

namespace App\Services;

use App\Models\Commande;
use App\Models\MouvementStockage;
use App\Models\Produit;
use App\Models\Stock;
use Illuminate\Validation\ValidationException;

/**
 * Règles métier du stock.
 *
 * - Produit.quantite = quantité actuellement disponible.
 * - Valider une production = créer un mouvement d'entrée (la quantité est déjà portée par le produit).
 * - Un mouvement manuel d'entrée / sortie ajuste Produit.quantite.
 * - Valider une commande crée une sortie par ligne et diminue Produit.quantite.
 */
class StockService
{
    public function validerProduction(Produit $produit, Stock $stock, ?int $userId): MouvementStockage
    {
        if ($produit->statut === 'validee') {
            throw ValidationException::withMessages(['produit' => ['Cette production est déjà validée.']]);
        }

        return MouvementStockage::create([
            'produit_id' => $produit->id,
            'stock_id' => $stock->id,
            'user_id' => $userId,
            'type_mouvement' => 'entree',
            'date_mouvement' => now()->toDateString(),
            'quantite' => $produit->quantite,
            'motif' => 'Validation de la production déclarée',
        ]);
    }

    public function mouvementManuel(Produit $produit, Stock $stock, string $type, float $quantite, ?int $userId, ?string $motif, ?string $date): MouvementStockage
    {
        if ($produit->statut !== 'validee') {
            throw ValidationException::withMessages(['produit_id' => ['La production doit être validée avant tout mouvement.']]);
        }

        if ($type === 'sortie' && $produit->quantite < $quantite) {
            throw ValidationException::withMessages(['quantite' => ["Stock insuffisant ({$produit->quantite} {$produit->unite} disponibles)."]]);
        }

        $produit->quantite = $type === 'entree' ? $produit->quantite + $quantite : $produit->quantite - $quantite;
        $produit->save();

        return MouvementStockage::create([
            'produit_id' => $produit->id,
            'stock_id' => $stock->id,
            'user_id' => $userId,
            'type_mouvement' => $type,
            'date_mouvement' => $date ?: now()->toDateString(),
            'quantite' => $quantite,
            'motif' => $motif,
        ]);
    }

    /** À appeler dans une transaction. Verrouille les produits et crée les sorties de stock. */
    public function sortieCommande(Commande $commande, ?int $userId): void
    {
        foreach ($commande->lignes()->get() as $ligne) {
            $produit = Produit::lockForUpdate()->with('mouvements')->findOrFail($ligne->produit_id);

            if ($produit->quantite < $ligne->quantite) {
                throw ValidationException::withMessages([
                    'stock' => ["Stock insuffisant pour « {$produit->nom_produit} » ({$produit->quantite} {$produit->unite} disponibles, {$ligne->quantite} demandés)."],
                ]);
            }

            $entree = $produit->mouvements->firstWhere('type_mouvement', 'entree');
            if (! $entree) {
                throw ValidationException::withMessages(['stock' => ["« {$produit->nom_produit} » n'est pas encore en stock."]]);
            }

            $produit->quantite = $produit->quantite - $ligne->quantite;
            $produit->save();

            MouvementStockage::create([
                'produit_id' => $produit->id,
                'stock_id' => $entree->stock_id,
                'user_id' => $userId,
                'type_mouvement' => 'sortie',
                'date_mouvement' => now()->toDateString(),
                'quantite' => $ligne->quantite,
                'motif' => "Vente — commande #{$commande->id}",
            ]);
        }
    }
}
