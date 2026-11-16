<?php

namespace Tests\Feature;

use App\Models\Membre;
use App\Models\Produit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Parcours métier de bout en bout : adhésion, production → stock, commande → vente,
 * logistique (livraison), et contrôle d'accès par rôle.
 *
 * Lancer : php artisan test --filter=CooperativeFlowTest
 */
class CooperativeFlowTest extends TestCase
{
    use RefreshDatabase;

    private function utilisateur(string $role, string $username, bool $membreActif = true): User
    {
        $user = User::create([
            'nom' => ucfirst($username), 'prenom' => 'Test', 'username' => $username,
            'telephone' => '770000000', 'role' => $role, 'password' => 'secret123',
        ]);
        if ($role === 'membre') {
            Membre::create(['user_id' => $user->id, 'statut' => $membreActif ? 'actif' : 'en_attente']);
        }

        return $user;
    }

    /** Un membre déclare une production de $quantite kg ; le magasinier la valide en entrepôt. */
    private function produitEnStock(User $membre, User $magasinier, float $quantite = 100): int
    {
        Sanctum::actingAs($membre);
        $parcelle = $this->postJson('/api/membre/parcelles', ['nom' => 'P1', 'superficie' => 2])->assertCreated()->json('id');
        $periode = $this->postJson('/api/membre/periodes', ['parcelle_id' => $parcelle, 'type' => 'Hivernage'])->assertCreated()->json('id');
        $culture = $this->postJson('/api/membre/cultures', ['periode_id' => $periode, 'designation' => 'Oignon'])->assertCreated()->json('id');
        $produit = $this->postJson('/api/membre/productions', [
            'culture_id' => $culture, 'nom_produit' => 'Oignon violet', 'quantite' => $quantite,
            'unite' => 'kg', 'prix_vente' => 500, 'date_entree' => '2026-11-01',
        ])->assertCreated()->json('id');

        Sanctum::actingAs($magasinier);
        $stock = $this->postJson('/api/magasinier/entrepots', ['nom_stock' => 'Entrepôt central', 'adresse' => 'Thiès'])->assertCreated()->json('id');
        $this->postJson("/api/magasinier/productions/{$produit}/valider", ['stock_id' => $stock])->assertOk();

        return $produit;
    }

    public function test_acces_refuse_sans_token_et_selon_le_role(): void
    {
        $this->getJson('/api/dashboard')->assertUnauthorized();

        Sanctum::actingAs($this->utilisateur('membre', 'moussa'));
        $this->getJson('/api/admin/utilisateurs')->assertForbidden();
        $this->getJson('/api/commercial/clients')->assertForbidden();
        $this->getJson('/api/logistique/livraisons')->assertForbidden();
    }

    public function test_adhesion_en_attente_puis_validee_par_ladmin(): void
    {
        $admin = $this->utilisateur('admin', 'admin');

        $this->postJson('/api/register', [
            'nom' => 'Sow', 'prenom' => 'Awa', 'username' => 'awa', 'telephone' => '771112233',
            'localite' => 'Kaolack', 'password' => 'secret123', 'password_confirmation' => 'secret123',
        ])->assertCreated();

        $this->postJson('/api/login', ['username' => 'awa', 'password' => 'secret123'])->assertForbidden();

        Sanctum::actingAs($admin);
        $awa = User::where('username', 'awa')->first();
        $this->postJson("/api/admin/utilisateurs/{$awa->id}/valider")->assertOk();

        // Nouvelle requête sans utilisateur authentifié par Sanctum::actingAs
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/login', ['username' => 'awa', 'password' => 'secret123'])
            ->assertOk()->assertJsonStructure(['token', 'user' => ['id', 'role']]);
    }

    public function test_production_validee_puis_vente_sort_du_stock(): void
    {
        $membre = $this->utilisateur('membre', 'moussa');
        $magasinier = $this->utilisateur('magasinier', 'mag');
        $commercial = $this->utilisateur('commercial', 'com');

        $produitId = $this->produitEnStock($membre, $magasinier, 100);
        $this->assertSame('validee', Produit::find($produitId)->statut);

        Sanctum::actingAs($commercial);
        $client = $this->postJson('/api/commercial/clients', ['nom_client' => 'Diallo', 'adresse' => 'Dakar'])->assertCreated()->json('id');

        // Commande de 30 kg au prix du catalogue (500) → 15 000
        $commande = $this->postJson('/api/commercial/commandes', [
            'client_id' => $client, 'lignes' => [['produit_id' => $produitId, 'quantite' => 30]],
        ])->assertCreated()->assertJsonPath('montant_total', 15000)->json('id');

        $this->postJson("/api/commercial/commandes/{$commande}/valider")->assertOk()->assertJsonPath('statut', 'validee');
        $this->assertEquals(70, Produit::find($produitId)->quantite);

        // Une commande supérieure au stock restant est refusée à la validation
        $trop = $this->postJson('/api/commercial/commandes', [
            'client_id' => $client, 'lignes' => [['produit_id' => $produitId, 'quantite' => 80]],
        ])->assertCreated()->json('id');
        $this->postJson("/api/commercial/commandes/{$trop}/valider")->assertStatus(422);
        $this->assertEquals(70, Produit::find($produitId)->quantite);

        // On ne revalide pas une commande déjà traitée
        $this->postJson("/api/commercial/commandes/{$commande}/valider")->assertStatus(422);
    }

    public function test_livraison_suit_le_flux_de_statuts(): void
    {
        $membre = $this->utilisateur('membre', 'moussa');
        $magasinier = $this->utilisateur('magasinier', 'mag');
        $commercial = $this->utilisateur('commercial', 'com');
        $produitId = $this->produitEnStock($membre, $magasinier, 100);

        Sanctum::actingAs($commercial);
        $client = $this->postJson('/api/commercial/clients', ['nom_client' => 'Diallo', 'adresse' => 'Dakar'])->assertCreated()->json('id');
        $commande = $this->postJson('/api/commercial/commandes', [
            'client_id' => $client, 'lignes' => [['produit_id' => $produitId, 'quantite' => 10]],
        ])->assertCreated()->json('id');

        // Une commande non validée ne peut pas être livrée
        $this->postJson('/api/logistique/livraisons', ['commande_id' => $commande, 'date_prevue' => '2026-11-20'])->assertStatus(422);

        $this->postJson("/api/commercial/commandes/{$commande}/valider")->assertOk();

        Sanctum::actingAs($magasinier);
        $vehicule = $this->postJson('/api/logistique/vehicules', ['immatriculation' => 'DK-123-AB', 'capacite_kg' => 1500])->assertCreated()->json('id');
        $chauffeur = $this->postJson('/api/logistique/chauffeurs', ['nom' => 'Gueye', 'prenom' => 'Modou'])->assertCreated()->json('id');

        $livraison = $this->postJson('/api/logistique/livraisons', [
            'commande_id' => $commande, 'vehicule_id' => $vehicule, 'chauffeur_id' => $chauffeur, 'date_prevue' => '2026-11-20',
        ])->assertCreated()->assertJsonPath('statut', 'planifiee')->assertJsonPath('adresse_livraison', 'Dakar')->json('id');

        // Pas de seconde livraison active pour la même commande
        $this->postJson('/api/logistique/livraisons', ['commande_id' => $commande, 'date_prevue' => '2026-11-21'])->assertStatus(422);

        // Saut de statut interdit : planifiée → livrée
        $this->postJson("/api/logistique/livraisons/{$livraison}/statut", ['statut' => 'livree'])->assertStatus(422);

        // Départ : véhicule et chauffeur deviennent indisponibles
        $this->postJson("/api/logistique/livraisons/{$livraison}/statut", ['statut' => 'en_transit'])->assertOk();
        $this->getJson('/api/logistique/vehicules')->assertJsonPath('0.disponible', false);

        // Arrivée : ressources libérées, date de livraison renseignée
        $this->postJson("/api/logistique/livraisons/{$livraison}/statut", ['statut' => 'livree'])
            ->assertOk()->assertJsonPath('statut', 'livree');
        $this->getJson('/api/logistique/vehicules')->assertJsonPath('0.disponible', true);

        // Livraison clôturée : plus de modification
        $this->postJson("/api/logistique/livraisons/{$livraison}/statut", ['statut' => 'annulee'])->assertStatus(422);
    }

    public function test_collecte_chez_un_producteur_et_visibilite_cote_membre(): void
    {
        $moussa = $this->utilisateur('membre', 'moussa');
        $awa = $this->utilisateur('membre', 'awa');
        $magasinier = $this->utilisateur('magasinier', 'mag');

        Sanctum::actingAs($magasinier);
        $collecte = $this->postJson('/api/logistique/collectes', [
            'membre_id' => $moussa->membre->id, 'lieu_collecte' => 'Village de Keur Massar',
            'date_prevue' => '2026-11-18', 'quantite_prevue' => 200,
        ])->assertCreated()->json('id');

        // La clôture exige la quantité réellement collectée
        $this->postJson("/api/logistique/collectes/{$collecte}/statut", ['statut' => 'en_cours'])->assertOk();
        $this->postJson("/api/logistique/collectes/{$collecte}/statut", ['statut' => 'terminee'])->assertStatus(422);
        $this->postJson("/api/logistique/collectes/{$collecte}/statut", ['statut' => 'terminee', 'quantite_collectee' => 185])
            ->assertOk()->assertJsonPath('statut', 'terminee');

        // Chaque membre ne voit que ses propres collectes
        Sanctum::actingAs($moussa);
        $this->getJson('/api/membre/collectes')->assertOk()->assertJsonCount(1);
        Sanctum::actingAs($awa);
        $this->getJson('/api/membre/collectes')->assertOk()->assertJsonCount(0);
    }
}
