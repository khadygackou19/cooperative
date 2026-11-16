<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Commande;
use App\Models\Produit;
use App\Services\StockService;
use App\Support\Journal;
use App\Support\Presenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Clients, commandes et ventes. */
class CommercialController extends Controller
{
    public function __construct(private StockService $stock)
    {
    }

    private const COMMANDE_RELATIONS = ['client', 'lignes.produit', 'livraison'];

    // ---------- Clients ----------

    public function clients(): JsonResponse
    {
        return response()->json(Client::withCount('commandes')->orderBy('nom_client')->get());
    }

    public function storeClient(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nom_client' => 'required|string|max:120',
            'prenom_client' => 'nullable|string|max:120',
            'telephone' => 'nullable|string|max:30',
            'adresse' => 'nullable|string|max:255',
        ]);

        $client = Client::create($data);
        Journal::log($request->user()->id, 'Ajout de client', 'Client', $client->id, $client->nom_client);

        return response()->json($client, 201);
    }

    public function updateClient(Request $request, Client $client): JsonResponse
    {
        $client->update($request->validate([
            'nom_client' => 'sometimes|required|string|max:120',
            'prenom_client' => 'nullable|string|max:120',
            'telephone' => 'nullable|string|max:30',
            'adresse' => 'nullable|string|max:255',
        ]));

        return response()->json($client);
    }

    // ---------- Produits disponibles à la vente ----------

    public function produitsDisponibles(): JsonResponse
    {
        $rows = Produit::valides()->where('quantite', '>', 0)->orderBy('nom_produit')->get()->map(fn ($p) => [
            'id' => $p->id,
            'nom_produit' => $p->nom_produit,
            'quantite' => $p->quantite,
            'unite' => $p->unite,
            'prix_vente' => $p->prix_vente,
        ]);

        return response()->json($rows->values());
    }

    // ---------- Commandes ----------

    public function commandes(Request $request): JsonResponse
    {
        $q = Commande::with(self::COMMANDE_RELATIONS)->latest('id');
        if ($statut = $request->query('statut')) {
            $q->where('statut', $statut);
        }

        return response()->json($q->get()->map(fn ($c) => Presenter::commande($c))->values());
    }

    public function showCommande(Commande $commande): JsonResponse
    {
        return response()->json(Presenter::commande($commande->load(self::COMMANDE_RELATIONS)));
    }

    public function storeCommande(Request $request): JsonResponse
    {
        $data = $request->validate([
            'client_id' => 'required|integer|exists:clients,id',
            'date_commande' => 'nullable|date',
            'lignes' => 'required|array|min:1',
            'lignes.*.produit_id' => 'required|integer|distinct|exists:produits,id',
            'lignes.*.quantite' => 'required|numeric|min:0.01',
            'lignes.*.prix_unitaire' => 'nullable|numeric|min:0',
        ]);

        $commande = DB::transaction(function () use ($data, $request) {
            $commande = Commande::create([
                'client_id' => $data['client_id'],
                'user_id' => $request->user()->id,
                'date_commande' => $data['date_commande'] ?? now()->toDateString(),
                'statut' => 'en_attente',
            ]);

            foreach ($data['lignes'] as $i => $l) {
                $produit = Produit::with('mouvements')->findOrFail($l['produit_id']);
                if ($produit->statut !== 'validee') {
                    throw ValidationException::withMessages(["lignes.$i.produit_id" => ["« {$produit->nom_produit} » n'est pas en stock."]]);
                }
                $commande->lignes()->create([
                    'produit_id' => $produit->id,
                    'quantite' => $l['quantite'],
                    'prix_unitaire' => $l['prix_unitaire'] ?? $produit->prix_vente,
                ]);
            }

            return $commande;
        });

        Journal::log($request->user()->id, 'Nouvelle commande', 'Commande', $commande->id);

        return response()->json(Presenter::commande($commande->load(self::COMMANDE_RELATIONS)), 201);
    }

    /** Valider = vente conclue : les quantités sortent du stock. */
    public function validerCommande(Request $request, Commande $commande): JsonResponse
    {
        DB::transaction(function () use ($commande, $request) {
            $commande = Commande::lockForUpdate()->findOrFail($commande->id);
            if ($commande->statut !== 'en_attente') {
                throw ValidationException::withMessages(['statut' => ['Cette commande a déjà été traitée.']]);
            }

            $this->stock->sortieCommande($commande, $request->user()->id);
            $commande->update(['statut' => 'validee']);
        });

        Journal::log($request->user()->id, 'Commande validée', 'Commande', $commande->id);

        return response()->json(Presenter::commande($commande->fresh(self::COMMANDE_RELATIONS)));
    }

    public function refuserCommande(Request $request, Commande $commande): JsonResponse
    {
        if ($commande->statut !== 'en_attente') {
            return response()->json(['message' => 'Cette commande a déjà été traitée.'], 422);
        }

        $commande->update(['statut' => 'refusee']);
        Journal::log($request->user()->id, 'Commande refusée', 'Commande', $commande->id);

        return response()->json(Presenter::commande($commande->fresh(self::COMMANDE_RELATIONS)));
    }

    // ---------- Ventes (= commandes validées) ----------

    public function ventes(): JsonResponse
    {
        $rows = Commande::where('statut', 'validee')->with(self::COMMANDE_RELATIONS)->latest('id')->get();

        return response()->json([
            'total' => $rows->sum(fn ($c) => $c->montant_total),
            'ventes' => $rows->map(fn ($c) => Presenter::commande($c))->values(),
        ]);
    }
}
