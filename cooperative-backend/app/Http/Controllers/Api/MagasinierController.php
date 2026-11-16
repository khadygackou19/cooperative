<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MouvementStockage;
use App\Models\Produit;
use App\Models\Stock;
use App\Services\StockService;
use App\Support\Journal;
use App\Support\Presenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Entrepôts, stocks, validation des productions et mouvements. */
class MagasinierController extends Controller
{
    public function __construct(private StockService $stock)
    {
    }

    private const PRODUIT_RELATIONS = ['culture.periode.parcelle.membre.user', 'mouvements.stock'];

    // ---------- Entrepôts ----------

    private function netParProduit(Stock $s)
    {
        return $s->mouvements->groupBy('produit_id')->map(
            fn ($g) => $g->sum(fn ($m) => $m->type_mouvement === 'entree' ? $m->quantite : -$m->quantite)
        );
    }

    public function entrepots(): JsonResponse
    {
        $rows = Stock::with('mouvements')->orderBy('nom_stock')->get()->map(function (Stock $s) {
            $net = $this->netParProduit($s);

            return [
                'id' => $s->id,
                'nom_stock' => $s->nom_stock,
                'adresse' => $s->adresse,
                'nb_produits' => $net->filter(fn ($q) => $q > 0)->count(),
                'quantite_totale' => round($net->sum(), 2),
            ];
        });

        return response()->json($rows->values());
    }

    public function storeEntrepot(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nom_stock' => 'required|string|max:120',
            'adresse' => 'nullable|string|max:255',
        ]);

        $stock = Stock::create($data);
        Journal::log($request->user()->id, "Création d'entrepôt", 'Stock', $stock->id, $stock->nom_stock);

        return response()->json($stock, 201);
    }

    public function updateEntrepot(Request $request, Stock $entrepot): JsonResponse
    {
        $entrepot->update($request->validate([
            'nom_stock' => 'sometimes|required|string|max:120',
            'adresse' => 'nullable|string|max:255',
        ]));

        return response()->json($entrepot);
    }

    /** Détail d'un entrepôt : produits stockés (quantité nette) + derniers mouvements. */
    public function showEntrepot(Stock $entrepot): JsonResponse
    {
        $entrepot->load('mouvements.produit');
        $net = $this->netParProduit($entrepot);

        $produits = $net->map(function ($qte, $produitId) use ($entrepot) {
            $p = $entrepot->mouvements->firstWhere('produit_id', $produitId)->produit;

            return [
                'produit_id' => $produitId,
                'nom_produit' => $p->nom_produit,
                'unite' => $p->unite,
                'quantite' => round($qte, 2),
            ];
        })->filter(fn ($r) => $r['quantite'] > 0)->values();

        $mouvements = $entrepot->mouvements->sortByDesc('id')->take(30)->map(fn ($m) => [
            'id' => $m->id,
            'date' => $m->date_mouvement?->format('Y-m-d'),
            'type' => $m->type_mouvement,
            'produit' => $m->produit?->nom_produit,
            'quantite' => $m->quantite,
            'motif' => $m->motif,
        ])->values();

        return response()->json([
            'id' => $entrepot->id,
            'nom_stock' => $entrepot->nom_stock,
            'adresse' => $entrepot->adresse,
            'quantite_totale' => round($produits->sum('quantite'), 2),
            'produits' => $produits,
            'mouvements' => $mouvements,
        ]);
    }

    // ---------- Stocks (produits validés) ----------

    public function stocks(): JsonResponse
    {
        $rows = Produit::valides()->with(self::PRODUIT_RELATIONS)->orderBy('nom_produit')->get();

        return response()->json($rows->map(fn ($p) => Presenter::produit($p))->values());
    }

    // ---------- Productions en attente ----------

    public function productionsEnAttente(): JsonResponse
    {
        $rows = Produit::enAttente()->with(self::PRODUIT_RELATIONS)->oldest()->get();

        return response()->json($rows->map(fn ($p) => Presenter::produit($p))->values());
    }

    public function validerProduction(Request $request, Produit $produit): JsonResponse
    {
        $data = $request->validate(['stock_id' => 'required|integer|exists:stocks,id']);

        $this->stock->validerProduction($produit->load('mouvements'), Stock::findOrFail($data['stock_id']), $request->user()->id);
        Journal::log($request->user()->id, 'Production validée', 'Produit', $produit->id, "{$produit->nom_produit} → entrepôt #{$data['stock_id']}");

        return response()->json(Presenter::produit($produit->fresh(self::PRODUIT_RELATIONS)));
    }

    /** Refuser = supprimer la déclaration (aucun mouvement n'est créé). */
    public function refuserProduction(Request $request, Produit $produit): JsonResponse
    {
        if ($produit->load('mouvements')->statut === 'validee') {
            return response()->json(['message' => 'Une production validée ne peut pas être refusée.'], 422);
        }

        Journal::log($request->user()->id, 'Production refusée', 'Produit', $produit->id, $produit->nom_produit);
        $produit->delete();

        return response()->json(['message' => 'Production refusée.']);
    }

    // ---------- Mouvements ----------

    public function mouvements(): JsonResponse
    {
        $rows = MouvementStockage::with(['produit', 'stock', 'user'])->latest('id')->limit(200)->get()->map(fn ($m) => [
            'id' => $m->id,
            'date' => $m->date_mouvement?->format('Y-m-d'),
            'type' => $m->type_mouvement,
            'produit' => $m->produit?->nom_produit,
            'unite' => $m->produit?->unite,
            'entrepot' => $m->stock?->nom_stock,
            'quantite' => $m->quantite,
            'motif' => $m->motif,
            'auteur' => Presenter::nom($m->user),
        ]);

        return response()->json($rows->values());
    }

    public function storeMouvement(Request $request): JsonResponse
    {
        $data = $request->validate([
            'produit_id' => 'required|integer|exists:produits,id',
            'stock_id' => 'required|integer|exists:stocks,id',
            'type_mouvement' => 'required|in:entree,sortie',
            'quantite' => 'required|numeric|min:0.01',
            'motif' => 'nullable|string|max:255',
            'date_mouvement' => 'nullable|date',
        ]);

        $m = $this->stock->mouvementManuel(
            Produit::with('mouvements')->findOrFail($data['produit_id']),
            Stock::findOrFail($data['stock_id']),
            $data['type_mouvement'],
            (float) $data['quantite'],
            $request->user()->id,
            $data['motif'] ?? null,
            $data['date_mouvement'] ?? null,
        );

        Journal::log($request->user()->id, 'Mouvement de stock ('.$data['type_mouvement'].')', 'MouvementStockage', $m->id);

        return response()->json($m, 201);
    }
}
