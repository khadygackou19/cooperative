<?php

namespace App\Support;

use App\Models\Collecte;
use App\Models\Commande;
use App\Models\Livraison;
use App\Models\Produit;
use App\Models\User;

/** Mise en forme des objets renvoyés par l'API (formats plats, faciles à afficher). */
class Presenter
{
    public static function nom(?User $u): ?string
    {
        return $u ? trim($u->prenom.' '.$u->nom) : null;
    }

    public static function user(User $u): array
    {
        $u->loadMissing('membre');

        return [
            'id' => $u->id,
            'nom' => $u->nom,
            'prenom' => $u->prenom,
            'username' => $u->username,
            'telephone' => $u->telephone,
            'matricule' => $u->matricule,
            'role' => $u->role,
            'statut' => $u->membre?->statut,
            'date_adhesion' => $u->membre?->date_adhesion?->format('Y-m-d'),
            'localite' => $u->membre?->localite,
            'membre_id' => $u->membre?->id,
        ];
    }

    /** Nécessite : culture.periode.parcelle.membre.user et mouvements.stock chargés. */
    public static function produit(Produit $p): array
    {
        $culture = $p->culture;
        $parcelle = $culture?->periode?->parcelle;

        return [
            'id' => $p->id,
            'nom_produit' => $p->nom_produit,
            'quantite' => $p->quantite,
            'unite' => $p->unite,
            'prix_vente' => $p->prix_vente,
            'date_entree' => $p->date_entree?->format('Y-m-d'),
            'statut' => $p->statut,
            'niveau' => $p->niveau,
            'culture' => $culture?->designation,
            'parcelle' => $parcelle?->nom ?? ($parcelle ? 'Parcelle #'.$parcelle->id : null),
            'producteur' => self::nom($parcelle?->membre?->user),
            'entrepot' => $p->entrepot()?->nom_stock,
        ];
    }

    /** Nécessite : client, lignes.produit, livraison chargés. */
    public static function commande(Commande $c): array
    {
        return [
            'id' => $c->id,
            'date_commande' => $c->date_commande?->format('Y-m-d'),
            'statut' => $c->statut,
            'client_id' => $c->client_id,
            'client' => $c->client ? trim($c->client->prenom_client.' '.$c->client->nom_client) : null,
            'client_adresse' => $c->client?->adresse,
            'montant_total' => $c->montant_total,
            'lignes' => $c->lignes->map(fn ($l) => [
                'produit_id' => $l->produit_id,
                'produit' => $l->produit?->nom_produit,
                'unite' => $l->produit?->unite,
                'quantite' => $l->quantite,
                'prix_unitaire' => $l->prix_unitaire,
                'sous_total' => $l->quantite * $l->prix_unitaire,
            ])->values(),
            'livraison_statut' => $c->livraison?->statut,
            'livraison_id' => $c->livraison?->id,
        ];
    }

    /** Nécessite : commande.client, vehicule, chauffeur chargés. */
    public static function livraison(Livraison $l): array
    {
        return [
            'id' => $l->id,
            'numero_bon' => $l->numero_bon,
            'commande_id' => $l->commande_id,
            'client' => $l->commande?->client ? trim($l->commande->client->prenom_client.' '.$l->commande->client->nom_client) : null,
            'montant' => $l->commande?->montant_total,
            'adresse_livraison' => $l->adresse_livraison,
            'date_prevue' => $l->date_prevue?->format('Y-m-d'),
            'date_livraison' => $l->date_livraison?->format('Y-m-d'),
            'statut' => $l->statut,
            'vehicule_id' => $l->vehicule_id,
            'vehicule' => $l->vehicule?->immatriculation,
            'chauffeur_id' => $l->chauffeur_id,
            'chauffeur' => $l->chauffeur ? trim($l->chauffeur->prenom.' '.$l->chauffeur->nom) : null,
            'observation' => $l->observation,
        ];
    }

    /** Nécessite : membre.user, produit, stock, vehicule, chauffeur chargés. */
    public static function collecte(Collecte $c): array
    {
        return [
            'id' => $c->id,
            'membre_id' => $c->membre_id,
            'producteur' => self::nom($c->membre?->user),
            'produit' => $c->produit?->nom_produit,
            'produit_id' => $c->produit_id,
            'lieu_collecte' => $c->lieu_collecte,
            'entrepot' => $c->stock?->nom_stock,
            'stock_id' => $c->stock_id,
            'date_prevue' => $c->date_prevue?->format('Y-m-d'),
            'quantite_prevue' => $c->quantite_prevue,
            'quantite_collectee' => $c->quantite_collectee,
            'statut' => $c->statut,
            'vehicule_id' => $c->vehicule_id,
            'vehicule' => $c->vehicule?->immatriculation,
            'chauffeur_id' => $c->chauffeur_id,
            'chauffeur' => $c->chauffeur ? trim($c->chauffeur->prenom.' '.$c->chauffeur->nom) : null,
            'observation' => $c->observation,
        ];
    }
}
