<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CommercialController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\LogistiqueController;
use App\Http\Controllers\Api\MagasinierController;
use App\Http\Controllers\Api\MembreEspaceController;
use App\Http\Controllers\Api\UtilisateurController;
use Illuminate\Support\Facades\Route;

// ---------- Public ----------
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);

// ---------- Authentifié (token Sanctum) ----------
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::put('/me', [AuthController::class, 'updateMe']);
    Route::get('/dashboard', DashboardController::class);

    // ----- Administrateur : utilisateurs + pilotage en lecture seule -----
    Route::prefix('admin')->middleware('role:admin')->group(function () {
        Route::get('/utilisateurs', [UtilisateurController::class, 'index']);
        Route::post('/utilisateurs', [UtilisateurController::class, 'store']);
        Route::put('/utilisateurs/{utilisateur}', [UtilisateurController::class, 'update']);
        Route::delete('/utilisateurs/{utilisateur}', [UtilisateurController::class, 'destroy']);
        Route::post('/utilisateurs/{utilisateur}/valider', [UtilisateurController::class, 'valider']);
        Route::post('/utilisateurs/{utilisateur}/refuser', [UtilisateurController::class, 'refuser']);

        Route::get('/productions', [AdminController::class, 'productions']);
        Route::get('/stocks', [AdminController::class, 'stocks']);
        Route::get('/commandes', [AdminController::class, 'commandes']);
        Route::get('/ventes', [AdminController::class, 'ventes']);
        Route::get('/livraisons', [AdminController::class, 'livraisons']);
        Route::get('/journal', [AdminController::class, 'journal']);
        Route::get('/rapports/synthese', [AdminController::class, 'rapportSynthese']);
    });

    // ----- Membre producteur -----
    Route::prefix('membre')->middleware('role:membre')->group(function () {
        Route::get('/parcelles', [MembreEspaceController::class, 'parcelles']);
        Route::post('/parcelles', [MembreEspaceController::class, 'storeParcelle']);
        Route::put('/parcelles/{parcelle}', [MembreEspaceController::class, 'updateParcelle']);
        Route::delete('/parcelles/{parcelle}', [MembreEspaceController::class, 'destroyParcelle']);

        Route::get('/periodes', [MembreEspaceController::class, 'periodes']);
        Route::post('/periodes', [MembreEspaceController::class, 'storePeriode']);

        Route::get('/cultures', [MembreEspaceController::class, 'cultures']);
        Route::post('/cultures', [MembreEspaceController::class, 'storeCulture']);

        Route::get('/productions', [MembreEspaceController::class, 'productions']);
        Route::post('/productions', [MembreEspaceController::class, 'storeProduction']);
        Route::delete('/productions/{produit}', [MembreEspaceController::class, 'destroyProduction']);

        Route::get('/collectes', [LogistiqueController::class, 'mesCollectes']);
    });

    // ----- Magasinier -----
    Route::prefix('magasinier')->middleware('role:magasinier')->group(function () {
        Route::get('/stocks', [MagasinierController::class, 'stocks']);

        Route::get('/entrepots', [MagasinierController::class, 'entrepots']);
        Route::post('/entrepots', [MagasinierController::class, 'storeEntrepot']);
        Route::get('/entrepots/{entrepot}', [MagasinierController::class, 'showEntrepot']);
        Route::put('/entrepots/{entrepot}', [MagasinierController::class, 'updateEntrepot']);

        Route::get('/productions-en-attente', [MagasinierController::class, 'productionsEnAttente']);
        Route::post('/productions/{produit}/valider', [MagasinierController::class, 'validerProduction']);
        Route::delete('/productions/{produit}', [MagasinierController::class, 'refuserProduction']);

        Route::get('/mouvements', [MagasinierController::class, 'mouvements']);
        Route::post('/mouvements', [MagasinierController::class, 'storeMouvement']);
    });

    // ----- Commercial -----
    Route::prefix('commercial')->middleware('role:commercial')->group(function () {
        Route::get('/clients', [CommercialController::class, 'clients']);
        Route::post('/clients', [CommercialController::class, 'storeClient']);
        Route::put('/clients/{client}', [CommercialController::class, 'updateClient']);

        Route::get('/produits-disponibles', [CommercialController::class, 'produitsDisponibles']);

        Route::get('/commandes', [CommercialController::class, 'commandes']);
        Route::post('/commandes', [CommercialController::class, 'storeCommande']);
        Route::get('/commandes/{commande}', [CommercialController::class, 'showCommande']);
        Route::post('/commandes/{commande}/valider', [CommercialController::class, 'validerCommande']);
        Route::post('/commandes/{commande}/refuser', [CommercialController::class, 'refuserCommande']);

        Route::get('/ventes', [CommercialController::class, 'ventes']);
    });

    // ----- Logistique : lecture (admin, magasinier, commercial) -----
    Route::prefix('logistique')->middleware('role:admin,magasinier,commercial')->group(function () {
        Route::get('/options', [LogistiqueController::class, 'options']);
        Route::get('/vehicules', [LogistiqueController::class, 'vehicules']);
        Route::get('/chauffeurs', [LogistiqueController::class, 'chauffeurs']);
        Route::get('/livraisons', [LogistiqueController::class, 'livraisons']);
        Route::get('/livraisons/{livraison}', [LogistiqueController::class, 'showLivraison']);
        Route::get('/collectes', [LogistiqueController::class, 'collectes']);
    });

    // ----- Logistique : écriture (magasinier, commercial) -----
    Route::prefix('logistique')->middleware('role:magasinier,commercial')->group(function () {
        Route::post('/vehicules', [LogistiqueController::class, 'storeVehicule']);
        Route::put('/vehicules/{vehicule}', [LogistiqueController::class, 'updateVehicule']);
        Route::delete('/vehicules/{vehicule}', [LogistiqueController::class, 'destroyVehicule']);

        Route::post('/chauffeurs', [LogistiqueController::class, 'storeChauffeur']);
        Route::put('/chauffeurs/{chauffeur}', [LogistiqueController::class, 'updateChauffeur']);
        Route::delete('/chauffeurs/{chauffeur}', [LogistiqueController::class, 'destroyChauffeur']);

        Route::post('/livraisons', [LogistiqueController::class, 'storeLivraison']);
        Route::put('/livraisons/{livraison}', [LogistiqueController::class, 'updateLivraison']);
        Route::post('/livraisons/{livraison}/statut', [LogistiqueController::class, 'statutLivraison']);

        Route::post('/collectes', [LogistiqueController::class, 'storeCollecte']);
        Route::post('/collectes/{collecte}/statut', [LogistiqueController::class, 'statutCollecte']);
    });
});
