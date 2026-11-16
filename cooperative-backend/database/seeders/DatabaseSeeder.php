<?php

namespace Database\Seeders;

use App\Models\Chauffeur;
use App\Models\Client;
use App\Models\Collecte;
use App\Models\Commande;
use App\Models\Culture;
use App\Models\Livraison;
use App\Models\Membre;
use App\Models\Parcelle;
use App\Models\Periode;
use App\Models\Produit;
use App\Models\Stock;
use App\Models\User;
use App\Models\Vehicule;
use App\Services\StockService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Données de démonstration. Comptes (mot de passe : "password") :
 *   admin / commercial / magasinier / moussa / awa  (ibrahima = adhésion en attente)
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $stockService = app(StockService::class);

            // ----- Utilisateurs -----
            $admin = User::create(['nom' => 'Diop', 'prenom' => 'Aminata', 'username' => 'admin', 'telephone' => '770000001', 'role' => 'admin', 'password' => 'password']);
            $commercial = User::create(['nom' => 'Ndiaye', 'prenom' => 'Cheikh', 'username' => 'commercial', 'telephone' => '770000002', 'matricule' => 'COM-001', 'role' => 'commercial', 'password' => 'password']);
            $magasinier = User::create(['nom' => 'Fall', 'prenom' => 'Ousmane', 'username' => 'magasinier', 'telephone' => '770000003', 'matricule' => 'MAG-001', 'role' => 'magasinier', 'password' => 'password']);

            $moussa = User::create(['nom' => 'Sow', 'prenom' => 'Moussa', 'username' => 'moussa', 'telephone' => '770000004', 'role' => 'membre', 'password' => 'password']);
            $awa = User::create(['nom' => 'Ba', 'prenom' => 'Awa', 'username' => 'awa', 'telephone' => '770000005', 'role' => 'membre', 'password' => 'password']);
            $ibrahima = User::create(['nom' => 'Sarr', 'prenom' => 'Ibrahima', 'username' => 'ibrahima', 'telephone' => '770000006', 'role' => 'membre', 'password' => 'password']);

            $mMoussa = Membre::create(['user_id' => $moussa->id, 'statut' => 'actif', 'date_adhesion' => now()->subMonths(8)->toDateString(), 'localite' => 'Thiès']);
            $mAwa = Membre::create(['user_id' => $awa->id, 'statut' => 'actif', 'date_adhesion' => now()->subMonths(5)->toDateString(), 'localite' => 'Kaolack']);
            Membre::create(['user_id' => $ibrahima->id, 'statut' => 'en_attente', 'localite' => 'Louga']);

            // ----- Entrepôts -----
            $central = Stock::create(['nom_stock' => 'Entrepôt central de Thiès', 'adresse' => 'Zone industrielle, Thiès']);
            $kaolack = Stock::create(['nom_stock' => 'Magasin de Kaolack', 'adresse' => 'Route de Nioro, Kaolack']);

            // ----- Parcelles / périodes / cultures / productions -----
            $p1 = Parcelle::create(['membre_id' => $mMoussa->id, 'nom' => 'Champ Nord', 'superficie' => 2.5, 'type_sol' => 'Sablo-argileux']);
            $per1 = Periode::create(['parcelle_id' => $p1->id, 'type' => 'Hivernage', 'variete' => 'Souna 3', 'date_debut' => now()->subMonths(5)->toDateString()]);
            $c1 = Culture::create(['periode_id' => $per1->id, 'designation' => 'Mil', 'statut_culture' => 'recoltee']);
            $mil = Produit::create(['culture_id' => $c1->id, 'nom_produit' => 'Mil', 'quantite' => 800, 'unite' => 'kg', 'prix_vente' => 350, 'date_entree' => now()->subDays(20)->toDateString()]);

            $p2 = Parcelle::create(['membre_id' => $mAwa->id, 'nom' => 'Périmètre maraîcher', 'superficie' => 1.2, 'type_sol' => 'Limoneux']);
            $per2 = Periode::create(['parcelle_id' => $p2->id, 'type' => 'Contre-saison', 'variete' => 'Violet de Galmi', 'date_debut' => now()->subMonths(4)->toDateString()]);
            $c2 = Culture::create(['periode_id' => $per2->id, 'designation' => 'Oignon', 'statut_culture' => 'recoltee']);
            $c3 = Culture::create(['periode_id' => $per2->id, 'designation' => 'Tomate', 'statut_culture' => 'recoltee']);
            $c4 = Culture::create(['periode_id' => $per1->id, 'designation' => 'Arachide', 'statut_culture' => 'recoltee']);

            $oignon = Produit::create(['culture_id' => $c2->id, 'nom_produit' => 'Oignon', 'quantite' => 420, 'unite' => 'kg', 'prix_vente' => 450, 'date_entree' => now()->subDays(15)->toDateString()]);
            $tomate = Produit::create(['culture_id' => $c3->id, 'nom_produit' => 'Tomate', 'quantite' => 40, 'unite' => 'kg', 'prix_vente' => 600, 'date_entree' => now()->subDays(10)->toDateString()]);
            Produit::create(['culture_id' => $c4->id, 'nom_produit' => 'Arachide', 'quantite' => 600, 'unite' => 'kg', 'prix_vente' => 500, 'date_entree' => now()->subDays(2)->toDateString()]); // reste en attente

            $stockService->validerProduction($mil->load('mouvements'), $central, $magasinier->id);
            $stockService->validerProduction($oignon->load('mouvements'), $central, $magasinier->id);
            $stockService->validerProduction($tomate->load('mouvements'), $kaolack, $magasinier->id);

            // ----- Clients et commandes -----
            $client1 = Client::create(['nom_client' => 'Gueye', 'prenom_client' => 'Fatou', 'telephone' => '780000001', 'adresse' => 'Marché Sandaga, Dakar']);
            $client2 = Client::create(['nom_client' => 'Épicerie Kër Mame', 'prenom_client' => null, 'telephone' => '780000002', 'adresse' => 'Rue 10, Mbour']);

            $cmd1 = Commande::create(['client_id' => $client1->id, 'user_id' => $commercial->id, 'date_commande' => now()->subDays(3)->toDateString(), 'statut' => 'en_attente']);
            $cmd1->lignes()->create(['produit_id' => $mil->id, 'quantite' => 100, 'prix_unitaire' => 350]);
            $cmd1->lignes()->create(['produit_id' => $oignon->id, 'quantite' => 50, 'prix_unitaire' => 450]);
            $stockService->sortieCommande($cmd1, $commercial->id);
            $cmd1->update(['statut' => 'validee']);

            $cmd2 = Commande::create(['client_id' => $client2->id, 'user_id' => $commercial->id, 'date_commande' => now()->toDateString(), 'statut' => 'en_attente']);
            $cmd2->lignes()->create(['produit_id' => $oignon->id, 'quantite' => 80, 'prix_unitaire' => 450]);

            // ----- Logistique -----
            $camion = Vehicule::create(['immatriculation' => 'DK-1234-AB', 'type' => 'Camion', 'capacite_kg' => 3000, 'disponible' => true]);
            Vehicule::create(['immatriculation' => 'TH-5678-CD', 'type' => 'Camionnette', 'capacite_kg' => 1000, 'disponible' => true]);
            $ch1 = Chauffeur::create(['nom' => 'Mbaye', 'prenom' => 'Lamine', 'telephone' => '760000001', 'numero_permis' => 'P-100245', 'disponible' => true]);
            Chauffeur::create(['nom' => 'Diallo', 'prenom' => 'Samba', 'telephone' => '760000002', 'numero_permis' => 'P-100388', 'disponible' => true]);

            Livraison::create([
                'numero_bon' => 'BL-DEMO-0001',
                'commande_id' => $cmd1->id,
                'vehicule_id' => $camion->id,
                'chauffeur_id' => $ch1->id,
                'user_id' => $commercial->id,
                'adresse_livraison' => $client1->adresse,
                'date_prevue' => now()->addDays(2)->toDateString(),
                'statut' => 'planifiee',
            ]);

            Collecte::create([
                'membre_id' => $mAwa->id,
                'produit_id' => $oignon->id,
                'stock_id' => $kaolack->id,
                'vehicule_id' => $camion->id,
                'chauffeur_id' => $ch1->id,
                'user_id' => $magasinier->id,
                'lieu_collecte' => 'Périmètre maraîcher, Kaolack',
                'date_prevue' => now()->addDays(4)->toDateString(),
                'quantite_prevue' => 300,
                'statut' => 'planifiee',
            ]);
        });
    }
}
