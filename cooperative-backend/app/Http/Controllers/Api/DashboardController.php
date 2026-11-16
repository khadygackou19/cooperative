<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Collecte;
use App\Models\Commande;
use App\Models\JournalAction;
use App\Models\LigneCommande;
use App\Models\Membre;
use App\Models\MouvementStockage;
use App\Models\Parcelle;
use App\Models\Produit;
use App\Models\Livraison;
use App\Support\Presenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** GET /dashboard : indicateurs adaptés au rôle de l'utilisateur connecté. */
class DashboardController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        return response()->json(match ($request->user()->role) {
            'admin' => $this->admin(),
            'membre' => $this->membre($request),
            'magasinier' => $this->magasinier(),
            'commercial' => $this->commercial(),
        });
    }

    private function revenus(): float
    {
        return (float) LigneCommande::whereHas('commande', fn ($c) => $c->where('statut', 'validee'))
            ->get()->sum(fn ($l) => $l->quantite * $l->prix_unitaire);
    }

    private function admin(): array
    {
        $seuil = config('coop.seuil_stock_bas', 50);

        return [
            'kpis' => [
                'membres_actifs' => Membre::where('statut', 'actif')->count(),
                'adhesions_en_attente' => Membre::where('statut', 'en_attente')->count(),
                'rendement_global' => (float) MouvementStockage::where('type_mouvement', 'entree')->sum('quantite'),
                'stock_total' => (float) Produit::valides()->sum('quantite'),
                'produits_en_rupture' => Produit::valides()->where('quantite', '<=', 0)->count(),
                'produits_niveau_faible' => Produit::valides()->where('quantite', '>', 0)->where('quantite', '<', $seuil)->count(),
                'revenus' => $this->revenus(),
                'commandes_en_attente' => Commande::where('statut', 'en_attente')->count(),
                'livraisons_en_cours' => Livraison::whereIn('statut', ['planifiee', 'en_preparation', 'en_transit'])->count(),
                'collectes_a_venir' => Collecte::whereIn('statut', ['planifiee', 'en_cours'])->count(),
            ],
            'activites' => JournalAction::with('user')->latest('id')->limit(10)->get()->map(fn ($j) => [
                'id' => $j->id,
                'date' => $j->created_at?->format('Y-m-d H:i'),
                'auteur' => Presenter::nom($j->user),
                'action' => $j->action,
                'details' => $j->details,
            ])->values(),
        ];
    }

    private function membre(Request $request): array
    {
        $membre = $request->user()->membre;
        $mine = fn ($q) => $q->where('membre_id', $membre?->id);
        $produits = Produit::whereHas('culture.periode.parcelle', $mine)->with('mouvements');
        $liste = $produits->get();

        return ['kpis' => [
            'parcelles' => Parcelle::where('membre_id', $membre?->id)->count(),
            'superficie_totale' => (float) Parcelle::where('membre_id', $membre?->id)->sum('superficie'),
            'productions_declarees' => $liste->count(),
            'productions_validees' => $liste->where('statut', 'validee')->count(),
            'productions_en_attente' => $liste->where('statut', 'en_attente')->count(),
            'quantite_produite' => (float) $liste->sum('quantite'),
            'collectes_prevues' => Collecte::where('membre_id', $membre?->id)->whereIn('statut', ['planifiee', 'en_cours'])->count(),
        ]];
    }

    private function magasinier(): array
    {
        $seuil = config('coop.seuil_stock_bas', 50);

        $alertes = Produit::valides()->where('quantite', '<', $seuil)->orderBy('quantite')->limit(10)->get()->map(fn ($p) => [
            'id' => $p->id,
            'nom_produit' => $p->nom_produit,
            'quantite' => $p->quantite,
            'unite' => $p->unite,
            'niveau' => $p->niveau,
        ]);

        return [
            'kpis' => [
                'productions_en_attente' => Produit::enAttente()->count(),
                'produits_en_stock' => Produit::valides()->where('quantite', '>', 0)->count(),
                'produits_en_rupture' => Produit::valides()->where('quantite', '<=', 0)->count(),
                'produits_niveau_faible' => Produit::valides()->where('quantite', '>', 0)->where('quantite', '<', $seuil)->count(),
                'collectes_a_venir' => Collecte::whereIn('statut', ['planifiee', 'en_cours'])->count(),
                'livraisons_a_preparer' => Livraison::whereIn('statut', ['planifiee', 'en_preparation'])->count(),
            ],
            'alertes' => $alertes->values(),
        ];
    }

    private function commercial(): array
    {
        return ['kpis' => [
            'commandes_en_attente' => Commande::where('statut', 'en_attente')->count(),
            'commandes_validees' => Commande::where('statut', 'validee')->count(),
            'commandes_refusees' => Commande::where('statut', 'refusee')->count(),
            'chiffre_affaires' => $this->revenus(),
            'livraisons_en_cours' => Livraison::whereIn('statut', ['planifiee', 'en_preparation', 'en_transit'])->count(),
            'commandes_a_livrer' => Commande::where('statut', 'validee')
                ->whereDoesntHave('livraisons', fn ($l) => $l->where('statut', '!=', 'annulee'))->count(),
        ]];
    }
}
