# Teranga Agri Coop — application complète

Deux dossiers :

- `cooperative-backend/` : API Laravel (fichiers à copier dans ton projet Laravel existant)
- `teranga-frontend/` : application Angular (projet complet)

## Espaces et fonctionnalités

| Rôle | Fonctionnalités |
|---|---|
| Admin | Tableau de bord, utilisateurs (validation des adhésions), pilotage lecture seule (productions, stocks, commandes, ventes, livraisons), journal des actions, rapport PDF |
| Membre | Parcelles, périodes, cultures, déclaration de productions, suivi de ses collectes |
| Magasinier | Stocks, entrepôts, validation des productions, mouvements, **logistique** (collectes, livraisons, véhicules, chauffeurs) |
| Commercial | Clients, commandes (validation = sortie de stock), ventes, **logistique** (livraisons, véhicules, chauffeurs) |

Règles clés : une production n'entre en stock qu'après validation du magasinier ; valider une commande crée les sorties de stock ; une livraison n'existe que pour une commande validée et suit le flux planifiée → en préparation → en transit → livrée ; un véhicule/chauffeur en mission est indisponible.

## Installation du backend (Laragon)

Dans ton projet Laravel 11 ou 12 `cooperative-backend` :

1. Copie le contenu de `cooperative-backend/` par-dessus ton projet (app, bootstrap, config, database, resources, routes, tests).
   Attention : `database/migrations/0001_01_01_000000_create_users_table.php` remplace la migration users par défaut (colonnes nom, prenom, username, role…).
2. `composer require barryvdh/laravel-dompdf` (si pas déjà installé)
3. `php artisan install:api` (installe Sanctum)
4. Configure MySQL dans `.env`, puis `php artisan migrate:fresh --seed`
5. `php artisan serve` (API sur http://localhost:8000/api)
6. Tests : `php artisan test`

Comptes de démonstration (mot de passe `password`) : `admin`, `commercial`, `magasinier`, `moussa`, `awa`, `ibrahima`.

## Installation du frontend

```
cd teranga-frontend
npm install
npm start        # http://localhost:4200
```

L'URL de l'API se change dans `src/app/core/config.ts`.

## État de la vérification

- Frontend : `ng build` passe sans erreur ; les 46 appels API correspondent aux 68 routes du backend.
- Backend : syntaxe PHP vérifiée et relations Eloquent contrôlées, mais **non exécuté** (Composer n'a pas accès à Packagist dans l'environnement de développement). Les tests `tests/Feature/CooperativeFlowTest.php` n'ont donc pas été lancés : lance `php artisan test` en premier et signale-moi toute erreur.
