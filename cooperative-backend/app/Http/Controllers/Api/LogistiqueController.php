<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Chauffeur;
use App\Models\Collecte;
use App\Models\Commande;
use App\Models\Livraison;
use App\Models\Membre;
use App\Models\Produit;
use App\Models\Stock;
use App\Models\Vehicule;
use App\Support\Journal;
use App\Support\Presenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Logistique : véhicules, chauffeurs, livraisons des commandes, collectes des récoltes.
 * Lecture : admin, magasinier, commercial. Écriture : magasinier, commercial.
 */
class LogistiqueController extends Controller
{
    private const LIVRAISON_RELATIONS = ['commande.client', 'commande.lignes', 'vehicule', 'chauffeur'];
    private const COLLECTE_RELATIONS = ['membre.user', 'produit', 'stock', 'vehicule', 'chauffeur'];

    // Transitions de statut autorisées
    private const LIVRAISON_FLUX = [
        'planifiee' => ['en_preparation', 'en_transit', 'annulee'],
        'en_preparation' => ['en_transit', 'annulee'],
        'en_transit' => ['livree', 'annulee'],
        'livree' => [],
        'annulee' => [],
    ];

    private const COLLECTE_FLUX = [
        'planifiee' => ['en_cours', 'annulee'],
        'en_cours' => ['terminee', 'annulee'],
        'terminee' => [],
        'annulee' => [],
    ];

    // ---------- Données pour les formulaires ----------

    public function options(): JsonResponse
    {
        $commandesALivrer = Commande::where('statut', 'validee')
            ->whereDoesntHave('livraisons', fn ($l) => $l->where('statut', '!=', 'annulee'))
            ->with(['client', 'lignes'])->latest('id')->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'libelle' => "Commande #{$c->id} — ".trim(($c->client->prenom_client ?? '').' '.$c->client->nom_client),
                'adresse' => $c->client->adresse,
            ]);

        return response()->json([
            'commandes' => $commandesALivrer->values(),
            'vehicules' => Vehicule::orderBy('immatriculation')->get(['id', 'immatriculation', 'type', 'capacite_kg', 'disponible']),
            'chauffeurs' => Chauffeur::orderBy('nom')->get(['id', 'nom', 'prenom', 'disponible']),
            'entrepots' => Stock::orderBy('nom_stock')->get(['id', 'nom_stock']),
            'membres' => Membre::where('statut', 'actif')->with('user')->get()->map(fn ($m) => [
                'id' => $m->id,
                'nom' => Presenter::nom($m->user),
                'localite' => $m->localite,
            ])->values(),
            'produits' => Produit::orderBy('nom_produit')->get(['id', 'nom_produit']),
        ]);
    }

    // ---------- Véhicules ----------

    public function vehicules(): JsonResponse
    {
        return response()->json(Vehicule::orderBy('immatriculation')->get());
    }

    public function storeVehicule(Request $request): JsonResponse
    {
        $data = $request->validate([
            'immatriculation' => 'required|string|max:30|unique:vehicules,immatriculation',
            'type' => 'nullable|string|max:60',
            'capacite_kg' => 'nullable|numeric|min:0',
        ]);

        $v = Vehicule::create($data + ['disponible' => true]);
        Journal::log($request->user()->id, 'Ajout de véhicule', 'Vehicule', $v->id, $v->immatriculation);

        return response()->json($v, 201);
    }

    public function updateVehicule(Request $request, Vehicule $vehicule): JsonResponse
    {
        $vehicule->update($request->validate([
            'immatriculation' => 'sometimes|required|string|max:30|unique:vehicules,immatriculation,'.$vehicule->id,
            'type' => 'nullable|string|max:60',
            'capacite_kg' => 'nullable|numeric|min:0',
            'disponible' => 'sometimes|boolean',
        ]));

        return response()->json($vehicule);
    }

    public function destroyVehicule(Vehicule $vehicule): JsonResponse
    {
        $vehicule->delete();

        return response()->json(['message' => 'Véhicule supprimé.']);
    }

    // ---------- Chauffeurs ----------

    public function chauffeurs(): JsonResponse
    {
        return response()->json(Chauffeur::orderBy('nom')->get());
    }

    public function storeChauffeur(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nom' => 'required|string|max:100',
            'prenom' => 'nullable|string|max:100',
            'telephone' => 'nullable|string|max:30',
            'numero_permis' => 'nullable|string|max:50',
        ]);

        $c = Chauffeur::create($data + ['disponible' => true]);
        Journal::log($request->user()->id, 'Ajout de chauffeur', 'Chauffeur', $c->id, $c->nom);

        return response()->json($c, 201);
    }

    public function updateChauffeur(Request $request, Chauffeur $chauffeur): JsonResponse
    {
        $chauffeur->update($request->validate([
            'nom' => 'sometimes|required|string|max:100',
            'prenom' => 'nullable|string|max:100',
            'telephone' => 'nullable|string|max:30',
            'numero_permis' => 'nullable|string|max:50',
            'disponible' => 'sometimes|boolean',
        ]));

        return response()->json($chauffeur);
    }

    public function destroyChauffeur(Chauffeur $chauffeur): JsonResponse
    {
        $chauffeur->delete();

        return response()->json(['message' => 'Chauffeur supprimé.']);
    }

    // ---------- Livraisons ----------

    public function livraisons(Request $request): JsonResponse
    {
        $q = Livraison::with(self::LIVRAISON_RELATIONS)->latest('id');
        if ($statut = $request->query('statut')) {
            $q->where('statut', $statut);
        }

        return response()->json($q->get()->map(fn ($l) => Presenter::livraison($l))->values());
    }

    public function showLivraison(Livraison $livraison): JsonResponse
    {
        return response()->json(Presenter::livraison($livraison->load(self::LIVRAISON_RELATIONS)));
    }

    public function storeLivraison(Request $request): JsonResponse
    {
        $data = $request->validate([
            'commande_id' => 'required|integer|exists:commandes,id',
            'vehicule_id' => 'nullable|integer|exists:vehicules,id',
            'chauffeur_id' => 'nullable|integer|exists:chauffeurs,id',
            'adresse_livraison' => 'nullable|string|max:255',
            'date_prevue' => 'required|date',
            'observation' => 'nullable|string|max:1000',
        ]);

        $commande = Commande::with('client')->findOrFail($data['commande_id']);

        if ($commande->statut !== 'validee') {
            throw ValidationException::withMessages(['commande_id' => ['Seule une commande validée peut être livrée.']]);
        }
        if ($commande->livraisons()->where('statut', '!=', 'annulee')->exists()) {
            throw ValidationException::withMessages(['commande_id' => ['Une livraison existe déjà pour cette commande.']]);
        }

        $adresse = $data['adresse_livraison'] ?? $commande->client->adresse;
        if (! $adresse) {
            throw ValidationException::withMessages(['adresse_livraison' => ["Indiquez l'adresse de livraison (le client n'en a pas)."]]);
        }

        $livraison = Livraison::create($data + [
            'adresse_livraison' => $adresse,
            'numero_bon' => 'BL-'.now()->format('Ymd').'-'.strtoupper(Str::random(4)),
            'user_id' => $request->user()->id,
            'statut' => 'planifiee',
        ]);

        Journal::log($request->user()->id, 'Livraison planifiée', 'Livraison', $livraison->id, $livraison->numero_bon);

        return response()->json(Presenter::livraison($livraison->load(self::LIVRAISON_RELATIONS)), 201);
    }

    /** Modification de la planification tant que la livraison n'est pas terminée. */
    public function updateLivraison(Request $request, Livraison $livraison): JsonResponse
    {
        if (in_array($livraison->statut, ['livree', 'annulee'], true)) {
            return response()->json(['message' => 'Cette livraison est clôturée.'], 422);
        }

        $livraison->update($request->validate([
            'vehicule_id' => 'nullable|integer|exists:vehicules,id',
            'chauffeur_id' => 'nullable|integer|exists:chauffeurs,id',
            'adresse_livraison' => 'sometimes|required|string|max:255',
            'date_prevue' => 'sometimes|required|date',
            'observation' => 'nullable|string|max:1000',
        ]));

        return response()->json(Presenter::livraison($livraison->fresh(self::LIVRAISON_RELATIONS)));
    }

    public function statutLivraison(Request $request, Livraison $livraison): JsonResponse
    {
        $data = $request->validate([
            'statut' => 'required|in:en_preparation,en_transit,livree,annulee',
            'observation' => 'nullable|string|max:1000',
        ]);

        $this->verifierTransition(self::LIVRAISON_FLUX, $livraison->statut, $data['statut']);

        DB::transaction(function () use ($livraison, $data) {
            if ($data['statut'] === 'en_transit') {
                $this->exigerRessourcesLibres($livraison);
                $this->occuper($livraison, false);
            }
            if (in_array($data['statut'], ['livree', 'annulee'], true)) {
                $this->occuper($livraison, true);
            }

            $livraison->update([
                'statut' => $data['statut'],
                'date_livraison' => $data['statut'] === 'livree' ? now()->toDateString() : $livraison->date_livraison,
                'observation' => $data['observation'] ?? $livraison->observation,
            ]);
        });

        Journal::log($request->user()->id, 'Livraison : '.$data['statut'], 'Livraison', $livraison->id, $livraison->numero_bon);

        return response()->json(Presenter::livraison($livraison->fresh(self::LIVRAISON_RELATIONS)));
    }

    // ---------- Collectes ----------

    public function collectes(Request $request): JsonResponse
    {
        $q = Collecte::with(self::COLLECTE_RELATIONS)->latest('id');
        if ($statut = $request->query('statut')) {
            $q->where('statut', $statut);
        }

        return response()->json($q->get()->map(fn ($c) => Presenter::collecte($c))->values());
    }

    /** Pour le membre : uniquement ses propres collectes. */
    public function mesCollectes(Request $request): JsonResponse
    {
        $membre = $request->user()->membre;
        abort_unless($membre, 403);

        $rows = Collecte::where('membre_id', $membre->id)->with(self::COLLECTE_RELATIONS)->latest('id')->get();

        return response()->json($rows->map(fn ($c) => Presenter::collecte($c))->values());
    }

    public function storeCollecte(Request $request): JsonResponse
    {
        $data = $request->validate([
            'membre_id' => 'required|integer|exists:membres,id',
            'produit_id' => 'nullable|integer|exists:produits,id',
            'stock_id' => 'nullable|integer|exists:stocks,id',
            'vehicule_id' => 'nullable|integer|exists:vehicules,id',
            'chauffeur_id' => 'nullable|integer|exists:chauffeurs,id',
            'lieu_collecte' => 'required|string|max:255',
            'date_prevue' => 'required|date',
            'quantite_prevue' => 'required|numeric|min:0',
            'observation' => 'nullable|string|max:1000',
        ]);

        $collecte = Collecte::create($data + ['user_id' => $request->user()->id, 'statut' => 'planifiee']);
        Journal::log($request->user()->id, 'Collecte planifiée', 'Collecte', $collecte->id);

        return response()->json(Presenter::collecte($collecte->load(self::COLLECTE_RELATIONS)), 201);
    }

    public function statutCollecte(Request $request, Collecte $collecte): JsonResponse
    {
        $data = $request->validate([
            'statut' => 'required|in:en_cours,terminee,annulee',
            'quantite_collectee' => 'required_if:statut,terminee|nullable|numeric|min:0',
            'observation' => 'nullable|string|max:1000',
        ]);

        $this->verifierTransition(self::COLLECTE_FLUX, $collecte->statut, $data['statut']);

        DB::transaction(function () use ($collecte, $data) {
            if ($data['statut'] === 'en_cours') {
                $this->exigerRessourcesLibres($collecte);
                $this->occuper($collecte, false);
            }
            if (in_array($data['statut'], ['terminee', 'annulee'], true)) {
                $this->occuper($collecte, true);
            }

            $collecte->update([
                'statut' => $data['statut'],
                'quantite_collectee' => $data['quantite_collectee'] ?? $collecte->quantite_collectee,
                'observation' => $data['observation'] ?? $collecte->observation,
            ]);
        });

        Journal::log($request->user()->id, 'Collecte : '.$data['statut'], 'Collecte', $collecte->id);

        return response()->json(Presenter::collecte($collecte->fresh(self::COLLECTE_RELATIONS)));
    }

    // ---------- Outils internes ----------

    private function verifierTransition(array $flux, string $de, string $vers): void
    {
        if (! in_array($vers, $flux[$de] ?? [], true)) {
            throw ValidationException::withMessages(['statut' => ["Passage impossible de « $de » à « $vers »."]]);
        }
    }

    /** Le véhicule et le chauffeur affectés doivent être libres au départ. */
    private function exigerRessourcesLibres(Livraison|Collecte $op): void
    {
        if ($op->vehicule_id && ! Vehicule::whereKey($op->vehicule_id)->value('disponible')) {
            throw ValidationException::withMessages(['vehicule_id' => ['Ce véhicule est déjà en mission.']]);
        }
        if ($op->chauffeur_id && ! Chauffeur::whereKey($op->chauffeur_id)->value('disponible')) {
            throw ValidationException::withMessages(['chauffeur_id' => ['Ce chauffeur est déjà en mission.']]);
        }
    }

    private function occuper(Livraison|Collecte $op, bool $disponible): void
    {
        if ($op->vehicule_id) {
            Vehicule::whereKey($op->vehicule_id)->update(['disponible' => $disponible]);
        }
        if ($op->chauffeur_id) {
            Chauffeur::whereKey($op->chauffeur_id)->update(['disponible' => $disponible]);
        }
    }
}
