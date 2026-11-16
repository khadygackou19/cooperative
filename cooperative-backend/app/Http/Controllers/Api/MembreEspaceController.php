<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Culture;
use App\Models\Parcelle;
use App\Models\Periode;
use App\Models\Produit;
use App\Support\Journal;
use App\Support\Presenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Espace du membre producteur : parcelles, périodes, cultures et productions.
 * Chaque membre ne voit et ne modifie que ses propres données.
 */
class MembreEspaceController extends Controller
{
    private function membreId(Request $request): int
    {
        $membre = $request->user()->membre;
        abort_unless($membre, 403, "Compte membre introuvable.");

        return $membre->id;
    }

    // ---------- Parcelles ----------

    public function parcelles(Request $request): JsonResponse
    {
        $rows = Parcelle::where('membre_id', $this->membreId($request))->withCount('periodes')->latest()->get();

        return response()->json($rows);
    }

    public function storeParcelle(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nom' => 'nullable|string|max:120',
            'superficie' => 'required|numeric|min:0.01',
            'type_sol' => 'nullable|string|max:80',
        ]);

        $parcelle = Parcelle::create($data + ['membre_id' => $this->membreId($request)]);
        Journal::log($request->user()->id, 'Déclaration de parcelle', 'Parcelle', $parcelle->id);

        return response()->json($parcelle, 201);
    }

    public function updateParcelle(Request $request, Parcelle $parcelle): JsonResponse
    {
        abort_unless($parcelle->membre_id === $this->membreId($request), 403);

        $parcelle->update($request->validate([
            'nom' => 'nullable|string|max:120',
            'superficie' => 'sometimes|required|numeric|min:0.01',
            'type_sol' => 'nullable|string|max:80',
        ]));

        return response()->json($parcelle);
    }

    public function destroyParcelle(Request $request, Parcelle $parcelle): JsonResponse
    {
        abort_unless($parcelle->membre_id === $this->membreId($request), 403);
        $parcelle->delete();

        return response()->json(['message' => 'Parcelle supprimée.']);
    }

    // ---------- Périodes ----------

    public function periodes(Request $request): JsonResponse
    {
        $membreId = $this->membreId($request);

        $rows = Periode::whereHas('parcelle', fn ($q) => $q->where('membre_id', $membreId))
            ->with('parcelle')->latest()->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'parcelle_id' => $p->parcelle_id,
                'parcelle' => $p->parcelle->nom ?? 'Parcelle #'.$p->parcelle_id,
                'type' => $p->type,
                'variete' => $p->variete,
                'date_debut' => $p->date_debut?->format('Y-m-d'),
            ]);

        return response()->json($rows->values());
    }

    public function storePeriode(Request $request): JsonResponse
    {
        $data = $request->validate([
            'parcelle_id' => 'required|integer|exists:parcelles,id',
            'type' => 'required|string|max:80',
            'variete' => 'nullable|string|max:120',
            'date_debut' => 'nullable|date',
        ]);

        abort_unless(Parcelle::find($data['parcelle_id'])->membre_id === $this->membreId($request), 403);

        $periode = Periode::create($data);
        Journal::log($request->user()->id, 'Déclaration de période', 'Periode', $periode->id);

        return response()->json($periode, 201);
    }

    // ---------- Cultures ----------

    public function cultures(Request $request): JsonResponse
    {
        $membreId = $this->membreId($request);

        $rows = Culture::whereHas('periode.parcelle', fn ($q) => $q->where('membre_id', $membreId))
            ->with('periode.parcelle')->latest()->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'periode_id' => $c->periode_id,
                'designation' => $c->designation,
                'statut_culture' => $c->statut_culture,
                'periode' => $c->periode->type.($c->periode->variete ? ' — '.$c->periode->variete : ''),
                'parcelle' => $c->periode->parcelle->nom ?? 'Parcelle #'.$c->periode->parcelle_id,
                'superficie' => (float) $c->periode->parcelle->superficie,
                'date_debut' => $c->periode->date_debut?->format('Y-m-d'),
            ]);

        return response()->json($rows->values());
    }

    public function storeCulture(Request $request): JsonResponse
    {
        $data = $request->validate([
            'periode_id' => 'required|integer|exists:periodes,id',
            'designation' => 'required|string|max:120',
            'statut_culture' => 'nullable|in:en_cours,recoltee',
        ]);

        $periode = Periode::with('parcelle')->find($data['periode_id']);
        abort_unless($periode->parcelle->membre_id === $this->membreId($request), 403);

        $culture = Culture::create($data + ['statut_culture' => $data['statut_culture'] ?? 'en_cours']);
        Journal::log($request->user()->id, 'Déclaration de culture', 'Culture', $culture->id, $culture->designation);

        return response()->json($culture, 201);
    }

    // ---------- Productions ----------

    public function productions(Request $request): JsonResponse
    {
        $membreId = $this->membreId($request);

        $rows = Produit::whereHas('culture.periode.parcelle', fn ($q) => $q->where('membre_id', $membreId))
            ->with(['culture.periode.parcelle.membre.user', 'mouvements.stock'])->latest()->get();

        return response()->json($rows->map(fn ($p) => Presenter::produit($p))->values());
    }

    public function storeProduction(Request $request): JsonResponse
    {
        $data = $request->validate([
            'culture_id' => 'required|integer|exists:cultures,id',
            'nom_produit' => 'required|string|max:120',
            'quantite' => 'required|numeric|min:0.01',
            'unite' => 'nullable|string|max:20',
            'prix_vente' => 'required|numeric|min:0',
            'date_entree' => 'required|date',
        ]);

        $culture = Culture::with('periode.parcelle')->find($data['culture_id']);
        abort_unless($culture->periode->parcelle->membre_id === $this->membreId($request), 403);

        $produit = Produit::create($data + ['unite' => $data['unite'] ?? 'kg']);
        $culture->update(['statut_culture' => 'recoltee']);
        Journal::log($request->user()->id, 'Déclaration de production', 'Produit', $produit->id, "{$produit->nom_produit} — {$produit->quantite} {$produit->unite}");

        return response()->json($produit, 201);
    }

    /** Annulation possible tant que la production n'est pas validée par le magasinier. */
    public function destroyProduction(Request $request, Produit $produit): JsonResponse
    {
        $produit->load('culture.periode.parcelle', 'mouvements');
        abort_unless($produit->culture->periode->parcelle->membre_id === $this->membreId($request), 403);

        if ($produit->statut === 'validee') {
            return response()->json(['message' => 'Une production validée ne peut plus être supprimée.'], 422);
        }

        $produit->delete();

        return response()->json(['message' => 'Déclaration supprimée.']);
    }
}
