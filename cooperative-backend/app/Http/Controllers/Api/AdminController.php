<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Commande;
use App\Models\JournalAction;
use App\Models\Livraison;
use App\Models\Produit;
use App\Support\Presenter;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/** Pilotage (lecture seule) et rapports PDF pour l'administrateur. */
class AdminController extends Controller
{
    private const PRODUIT_RELATIONS = ['culture.periode.parcelle.membre.user', 'mouvements.stock'];
    private const COMMANDE_RELATIONS = ['client', 'lignes.produit', 'livraison'];

    public function productions(): JsonResponse
    {
        $rows = Produit::with(self::PRODUIT_RELATIONS)->latest('id')->get();

        return response()->json([
            'indicateurs' => [
                'declarees' => $rows->count(),
                'validees' => $rows->where('statut', 'validee')->count(),
                'en_attente' => $rows->where('statut', 'en_attente')->count(),
            ],
            'donnees' => $rows->map(fn ($p) => Presenter::produit($p))->values(),
        ]);
    }

    public function stocks(): JsonResponse
    {
        $rows = Produit::valides()->with(self::PRODUIT_RELATIONS)->orderBy('nom_produit')->get();

        return response()->json([
            'indicateurs' => [
                'references' => $rows->count(),
                'quantite_totale' => $rows->sum('quantite'),
                'en_rupture' => $rows->where('niveau', 'rupture')->count(),
                'niveau_faible' => $rows->where('niveau', 'faible')->count(),
            ],
            'donnees' => $rows->map(fn ($p) => Presenter::produit($p))->values(),
        ]);
    }

    public function commandes(): JsonResponse
    {
        $rows = Commande::with(self::COMMANDE_RELATIONS)->latest('id')->get();

        return response()->json([
            'indicateurs' => [
                'total' => $rows->count(),
                'en_attente' => $rows->where('statut', 'en_attente')->count(),
                'validees' => $rows->where('statut', 'validee')->count(),
                'refusees' => $rows->where('statut', 'refusee')->count(),
            ],
            'donnees' => $rows->map(fn ($c) => Presenter::commande($c))->values(),
        ]);
    }

    public function ventes(): JsonResponse
    {
        $rows = Commande::where('statut', 'validee')->with(self::COMMANDE_RELATIONS)->latest('id')->get();
        $total = $rows->sum(fn ($c) => $c->montant_total);

        return response()->json([
            'indicateurs' => [
                'nombre_ventes' => $rows->count(),
                'chiffre_affaires' => $total,
                'panier_moyen' => $rows->count() ? round($total / $rows->count(), 2) : 0,
            ],
            'donnees' => $rows->map(fn ($c) => Presenter::commande($c))->values(),
        ]);
    }

    public function livraisons(): JsonResponse
    {
        $rows = Livraison::with(['commande.client', 'commande.lignes', 'vehicule', 'chauffeur'])->latest('id')->get();

        return response()->json([
            'indicateurs' => [
                'total' => $rows->count(),
                'en_cours' => $rows->whereIn('statut', ['planifiee', 'en_preparation', 'en_transit'])->count(),
                'livrees' => $rows->where('statut', 'livree')->count(),
                'annulees' => $rows->where('statut', 'annulee')->count(),
            ],
            'donnees' => $rows->map(fn ($l) => Presenter::livraison($l))->values(),
        ]);
    }

    public function journal(): JsonResponse
    {
        $rows = JournalAction::with('user')->latest('id')->limit(300)->get()->map(fn ($j) => [
            'id' => $j->id,
            'date' => $j->created_at?->format('Y-m-d H:i'),
            'auteur' => Presenter::nom($j->user) ?? '—',
            'action' => $j->action,
            'entite' => $j->entite,
            'details' => $j->details,
        ]);

        return response()->json($rows->values());
    }

    /** GET /admin/rapports/synthese : rapport PDF de synthèse de la coopérative. */
    public function rapportSynthese(): Response
    {
        $produits = Produit::valides()->with(self::PRODUIT_RELATIONS)->orderBy('nom_produit')->get();
        $ventes = Commande::where('statut', 'validee')->with(self::COMMANDE_RELATIONS)->latest('id')->get();
        $livraisons = Livraison::with(['commande.client', 'vehicule', 'chauffeur'])->latest('id')->limit(50)->get();

        $pdf = Pdf::loadView('rapports.synthese', [
            'date' => now()->format('d/m/Y H:i'),
            'produits' => $produits,
            'ventes' => $ventes,
            'livraisons' => $livraisons,
            'chiffre_affaires' => $ventes->sum(fn ($c) => $c->montant_total),
        ]);

        return $pdf->download('rapport-synthese-teranga-agri-coop.pdf');
    }
}
