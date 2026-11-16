import { fmtMoney, fmtNumber } from '../core/format';
import { Col, Field, Kpi, PageConfig, RowAction } from '../core/models';

const today = () => new Date().toISOString().slice(0, 10);

/** Indicateurs : [clé renvoyée par l'API, libellé, type d'affichage]. */
const indicators =
  (defs: [string, string, ('money' | 'n')?][], from = 'indicateurs') =>
  (resp: any): Kpi[] =>
    defs.map(([key, label, kind]) => ({
      label,
      value: kind === 'money' ? fmtMoney(resp[from]?.[key]) : fmtNumber(resp[from]?.[key]),
    }));

/** Liste déroulante alimentée par GET /logistique/options. */
const logistiqueOption = (path: string, label: string | ((r: any) => string), value = 'id') => ({
  endpoint: '/logistique/options',
  path,
  value,
  label,
});

const nomComplet = (r: any) => `${r.prenom ?? ''} ${r.nom ?? ''}`.trim();

// ---------- Colonnes réutilisées ----------
const colsProduits: Col[] = [
  { key: 'nom_produit', label: 'Produit' },
  { key: 'producteur', label: 'Producteur' },
  { key: 'culture', label: 'Culture' },
  { key: 'quantite', label: 'Quantité', type: 'qty', unitKey: 'unite' },
  { key: 'prix_vente', label: 'Prix de vente', type: 'money' },
  { key: 'date_entree', label: 'Déclarée le', type: 'date' },
  { key: 'statut', label: 'Statut', type: 'badge' },
];

const colsStock: Col[] = [
  { key: 'nom_produit', label: 'Produit' },
  { key: 'producteur', label: 'Producteur' },
  { key: 'entrepot', label: 'Entrepôt' },
  { key: 'quantite', label: 'Quantité', type: 'qty', unitKey: 'unite' },
  { key: 'prix_vente', label: 'Prix de vente', type: 'money' },
  { key: 'niveau', label: 'Niveau', type: 'badge' },
];

const colsCommandes: Col[] = [
  { key: 'id', label: 'N°' },
  { key: 'date_commande', label: 'Date', type: 'date' },
  { key: 'client', label: 'Client' },
  { key: 'lignes', label: 'Produits', type: 'lignes' },
  { key: 'montant_total', label: 'Montant', type: 'money' },
  { key: 'statut', label: 'Statut', type: 'badge' },
  { key: 'livraison_statut', label: 'Livraison', type: 'badge' },
];

const colsLivraisons: Col[] = [
  { key: 'numero_bon', label: 'Bon de livraison' },
  { key: 'client', label: 'Client' },
  { key: 'adresse_livraison', label: 'Adresse' },
  { key: 'date_prevue', label: 'Date prévue', type: 'date' },
  { key: 'date_livraison', label: 'Livrée le', type: 'date' },
  { key: 'vehicule', label: 'Véhicule' },
  { key: 'chauffeur', label: 'Chauffeur' },
  { key: 'statut', label: 'Statut', type: 'badge' },
];

// ======================================================================
//  ADMINISTRATEUR
// ======================================================================
export const ADMIN_UTILISATEURS: PageConfig = {
  title: 'Utilisateurs',
  subtitle: "Comptes de la plateforme et demandes d'adhésion des agriculteurs.",
  endpoint: '/admin/utilisateurs',
  columns: [
    { key: 'prenom', label: 'Prénom' },
    { key: 'nom', label: 'Nom' },
    { key: 'username', label: 'Identifiant' },
    { key: 'telephone', label: 'Téléphone' },
    { key: 'role', label: 'Rôle', type: 'badge' },
    { key: 'statut', label: 'Adhésion', type: 'badge' },
    { key: 'date_adhesion', label: "Date d'adhésion", type: 'date' },
  ],
  create: {
    label: 'Ajouter un utilisateur',
    endpoint: '/admin/utilisateurs',
    success: 'Utilisateur créé.',
    form: {
      title: 'Ajouter un utilisateur',
      fields: [
        { key: 'prenom', label: 'Prénom', type: 'text', required: true },
        { key: 'nom', label: 'Nom', type: 'text', required: true },
        { key: 'username', label: 'Identifiant de connexion', type: 'text', required: true },
        { key: 'telephone', label: 'Téléphone', type: 'tel' },
        {
          key: 'role',
          label: 'Rôle',
          type: 'select',
          required: true,
          options: [
            { value: 'admin', label: 'Administrateur' },
            { value: 'commercial', label: 'Commercial' },
            { value: 'magasinier', label: 'Magasinier' },
            { value: 'membre', label: 'Membre producteur' },
          ],
        },
        { key: 'localite', label: 'Localité (membre)', type: 'text' },
        { key: 'password', label: 'Mot de passe (6 caractères min.)', type: 'password', required: true },
      ],
    },
  },
  actions: [
    { label: 'Valider', kind: 'call', method: 'post', url: '/admin/utilisateurs/{id}/valider', style: 'primary', when: (r) => r.statut === 'en_attente', success: 'Adhésion validée.' },
    { label: 'Refuser', kind: 'call', method: 'post', url: '/admin/utilisateurs/{id}/refuser', style: 'danger', confirm: "Refuser cette demande d'adhésion ?", when: (r) => r.statut === 'en_attente', success: 'Demande refusée.' },
    { label: 'Supprimer', kind: 'call', method: 'delete', url: '/admin/utilisateurs/{id}', style: 'danger', confirm: 'Supprimer définitivement cet utilisateur ?', success: 'Utilisateur supprimé.' },
  ],
};

export const ADMIN_PRODUCTIONS: PageConfig = {
  title: 'Productions',
  subtitle: 'Suivi des productions déclarées par les membres (lecture seule).',
  endpoint: '/admin/productions',
  columns: colsProduits,
  indicators: indicators([['declarees', 'Déclarées'], ['validees', 'Validées'], ['en_attente', 'En attente']]),
};

export const ADMIN_STOCKS: PageConfig = {
  title: 'Stocks',
  subtitle: 'Niveaux de stock de la coopérative (lecture seule).',
  endpoint: '/admin/stocks',
  columns: colsStock,
  indicators: indicators([['references', 'Références'], ['quantite_totale', 'Quantité totale (kg)'], ['en_rupture', 'En rupture'], ['niveau_faible', 'Niveau faible']]),
};

export const ADMIN_COMMANDES: PageConfig = {
  title: 'Commandes',
  subtitle: 'Commandes des clients (lecture seule).',
  endpoint: '/admin/commandes',
  columns: colsCommandes,
  indicators: indicators([['total', 'Total'], ['en_attente', 'En attente'], ['validees', 'Validées'], ['refusees', 'Refusées']]),
};

export const ADMIN_VENTES: PageConfig = {
  title: 'Ventes',
  subtitle: 'Ventes conclues, c’est-à-dire commandes validées (lecture seule).',
  endpoint: '/admin/ventes',
  columns: colsCommandes.filter((c) => c.key !== 'statut'),
  indicators: indicators([['nombre_ventes', 'Nombre de ventes'], ['chiffre_affaires', "Chiffre d'affaires", 'money'], ['panier_moyen', 'Panier moyen', 'money']]),
};

export const ADMIN_LIVRAISONS: PageConfig = {
  title: 'Livraisons',
  subtitle: 'Suivi des livraisons aux clients (lecture seule).',
  endpoint: '/admin/livraisons',
  columns: colsLivraisons,
  indicators: indicators([['total', 'Total'], ['en_cours', 'En cours'], ['livrees', 'Livrées'], ['annulees', 'Annulées']]),
};

export const ADMIN_JOURNAL: PageConfig = {
  title: 'Journal des actions',
  subtitle: 'Traçabilité : qui a fait quoi, et quand.',
  endpoint: '/admin/journal',
  columns: [
    { key: 'date', label: 'Date', type: 'date' },
    { key: 'auteur', label: 'Auteur' },
    { key: 'action', label: 'Action' },
    { key: 'entite', label: 'Objet' },
    { key: 'details', label: 'Détails' },
  ],
};

// ======================================================================
//  MEMBRE PRODUCTEUR
// ======================================================================
export const MEMBRE_PARCELLES: PageConfig = {
  title: 'Mes parcelles',
  subtitle: 'Les terres que vous exploitez.',
  endpoint: '/membre/parcelles',
  emptyText: "Vous n'avez pas encore déclaré de parcelle.",
  columns: [
    { key: 'nom', label: 'Nom' },
    { key: 'superficie', label: 'Superficie (ha)', type: 'number' },
    { key: 'type_sol', label: 'Type de sol' },
    { key: 'periodes_count', label: 'Périodes', type: 'number' },
  ],
  create: {
    label: 'Déclarer une parcelle',
    endpoint: '/membre/parcelles',
    success: 'Parcelle enregistrée.',
    form: {
      title: 'Déclarer une parcelle',
      fields: [
        { key: 'nom', label: 'Nom de la parcelle', type: 'text', placeholder: 'Ex. Champ Nord' },
        { key: 'superficie', label: 'Superficie (hectares)', type: 'number', required: true },
        { key: 'type_sol', label: 'Type de sol', type: 'text', placeholder: 'Ex. Sablo-argileux' },
      ],
    },
  },
  actions: [{ label: 'Supprimer', kind: 'call', method: 'delete', url: '/membre/parcelles/{id}', style: 'danger', confirm: 'Supprimer cette parcelle et tout ce qui lui est rattaché ?', success: 'Parcelle supprimée.' }],
};

export const MEMBRE_PERIODES: PageConfig = {
  title: 'Mes périodes',
  subtitle: 'Les campagnes de culture sur vos parcelles.',
  endpoint: '/membre/periodes',
  emptyText: "Aucune période déclarée. Déclarez d'abord une parcelle.",
  columns: [
    { key: 'parcelle', label: 'Parcelle' },
    { key: 'type', label: 'Type de période' },
    { key: 'variete', label: 'Variété' },
    { key: 'date_debut', label: 'Début', type: 'date' },
  ],
  create: {
    label: 'Déclarer une période',
    endpoint: '/membre/periodes',
    success: 'Période enregistrée.',
    form: {
      title: 'Déclarer une période',
      fields: [
        { key: 'parcelle_id', label: 'Parcelle', type: 'select', required: true, optionsFrom: { endpoint: '/membre/parcelles', value: 'id', label: (r) => `${r.nom ?? 'Parcelle #' + r.id} (${r.superficie} ha)` } },
        { key: 'type', label: 'Type de période', type: 'text', required: true, placeholder: 'Hivernage, contre-saison…' },
        { key: 'variete', label: 'Variété', type: 'text' },
        { key: 'date_debut', label: 'Date de début', type: 'date', default: today() },
      ],
    },
  },
};

export const MEMBRE_CULTURES: PageConfig = {
  title: 'Mes cultures',
  subtitle: 'Les cultures démarrées sur vos parcelles.',
  endpoint: '/membre/cultures',
  emptyText: "Aucune culture déclarée. Déclarez d'abord une période.",
  columns: [
    { key: 'designation', label: 'Culture' },
    { key: 'periode', label: 'Période' },
    { key: 'parcelle', label: 'Parcelle' },
    { key: 'superficie', label: 'Superficie (ha)', type: 'number' },
    { key: 'date_debut', label: 'Début', type: 'date' },
    { key: 'statut_culture', label: 'Statut', type: 'badge' },
  ],
  create: {
    label: 'Déclarer une culture',
    endpoint: '/membre/cultures',
    success: 'Culture enregistrée.',
    form: {
      title: 'Déclarer une culture',
      fields: [
        { key: 'periode_id', label: 'Période', type: 'select', required: true, optionsFrom: { endpoint: '/membre/periodes', value: 'id', label: (r) => `${r.type}${r.variete ? ' — ' + r.variete : ''} · ${r.parcelle}` } },
        { key: 'designation', label: 'Désignation de la culture', type: 'text', required: true, placeholder: 'Ex. Oignon' },
      ],
    },
  },
};

export const MEMBRE_PRODUCTIONS: PageConfig = {
  title: 'Mes productions',
  subtitle: 'Les récoltes déclarées. Le magasinier les valide avant leur entrée en stock.',
  endpoint: '/membre/productions',
  emptyText: "Aucune production déclarée. Déclarez d'abord une culture.",
  columns: colsProduits.filter((c) => c.key !== 'producteur'),
  create: {
    label: 'Déclarer une production',
    endpoint: '/membre/productions',
    success: 'Production déclarée. Elle sera validée par le magasinier.',
    form: {
      title: 'Déclarer une production',
      fields: [
        { key: 'culture_id', label: 'Culture', type: 'select', required: true, optionsFrom: { endpoint: '/membre/cultures', value: 'id', label: (r) => `${r.designation} — ${r.parcelle}` } },
        { key: 'nom_produit', label: 'Nom du produit', type: 'text', required: true },
        { key: 'quantite', label: 'Quantité récoltée', type: 'number', required: true },
        { key: 'unite', label: 'Unité', type: 'text', default: 'kg' },
        { key: 'prix_vente', label: 'Prix de vente unitaire (FCFA)', type: 'number', required: true },
        { key: 'date_entree', label: 'Date de production', type: 'date', required: true, default: today() },
      ],
    },
  },
  actions: [{ label: 'Annuler', kind: 'call', method: 'delete', url: '/membre/productions/{id}', style: 'danger', confirm: 'Annuler cette déclaration ?', when: (r) => r.statut === 'en_attente', success: 'Déclaration supprimée.' }],
};

const colsCollectes: Col[] = [
  { key: 'date_prevue', label: 'Date prévue', type: 'date' },
  { key: 'producteur', label: 'Producteur' },
  { key: 'lieu_collecte', label: 'Lieu' },
  { key: 'produit', label: 'Produit' },
  { key: 'quantite_prevue', label: 'Qté prévue', type: 'number' },
  { key: 'quantite_collectee', label: 'Qté collectée', type: 'number' },
  { key: 'entrepot', label: 'Vers' },
  { key: 'vehicule', label: 'Véhicule' },
  { key: 'chauffeur', label: 'Chauffeur' },
  { key: 'statut', label: 'Statut', type: 'badge' },
];

export const MEMBRE_COLLECTES: PageConfig = {
  title: 'Mes collectes',
  subtitle: 'Passages de la coopérative pour récupérer vos récoltes.',
  endpoint: '/membre/collectes',
  emptyText: 'Aucune collecte planifiée pour le moment.',
  columns: colsCollectes.filter((c) => c.key !== 'producteur'),
};

// ======================================================================
//  MAGASINIER
// ======================================================================
export const MAG_STOCKS: PageConfig = {
  title: 'Stocks',
  subtitle: 'Produits en stock, avec alertes de rupture et de niveau faible.',
  endpoint: '/magasinier/stocks',
  emptyText: "Aucun produit en stock. Validez d'abord des productions.",
  columns: colsStock,
};

export const MAG_ENTREPOTS: PageConfig = {
  title: 'Entrepôts',
  subtitle: 'Lieux de stockage de la coopérative.',
  endpoint: '/magasinier/entrepots',
  columns: [
    { key: 'nom_stock', label: 'Nom' },
    { key: 'adresse', label: 'Adresse' },
    { key: 'nb_produits', label: 'Produits stockés', type: 'number' },
    { key: 'quantite_totale', label: 'Quantité totale', type: 'number' },
  ],
  create: {
    label: 'Ajouter un entrepôt',
    endpoint: '/magasinier/entrepots',
    success: 'Entrepôt ajouté.',
    form: {
      title: 'Ajouter un entrepôt',
      fields: [
        { key: 'nom_stock', label: "Nom de l'entrepôt", type: 'text', required: true },
        { key: 'adresse', label: 'Adresse', type: 'text' },
      ],
    },
  },
  actions: [{ label: 'Voir', kind: 'link', link: '/magasinier/entrepots/{id}', style: 'ghost' }],
};

export const MAG_PRODUCTIONS: PageConfig = {
  title: 'Productions en attente',
  subtitle: "Validez les récoltes déclarées par les membres pour les faire entrer en stock.",
  endpoint: '/magasinier/productions-en-attente',
  emptyText: 'Aucune production en attente de validation.',
  columns: colsProduits.filter((c) => c.key !== 'statut'),
  actions: [
    {
      label: 'Valider',
      kind: 'form',
      method: 'post',
      url: '/magasinier/productions/{id}/valider',
      style: 'primary',
      success: 'Production validée et ajoutée au stock.',
      form: {
        title: "Valider la production — choix de l'entrepôt",
        submitLabel: 'Valider',
        fields: [{ key: 'stock_id', label: "Entrepôt de réception", type: 'select', required: true, optionsFrom: { endpoint: '/magasinier/entrepots', value: 'id', label: 'nom_stock' } }],
      },
    },
    { label: 'Refuser', kind: 'call', method: 'delete', url: '/magasinier/productions/{id}', style: 'danger', confirm: 'Refuser et supprimer cette déclaration ?', success: 'Production refusée.' },
  ],
};

export const MAG_MOUVEMENTS: PageConfig = {
  title: 'Mouvements de stock',
  subtitle: 'Historique des entrées et sorties.',
  endpoint: '/magasinier/mouvements',
  columns: [
    { key: 'date', label: 'Date', type: 'date' },
    { key: 'type', label: 'Type', type: 'badge' },
    { key: 'produit', label: 'Produit' },
    { key: 'entrepot', label: 'Entrepôt' },
    { key: 'quantite', label: 'Quantité', type: 'qty', unitKey: 'unite' },
    { key: 'motif', label: 'Motif' },
    { key: 'auteur', label: 'Par' },
  ],
  create: {
    label: 'Enregistrer un mouvement',
    endpoint: '/magasinier/mouvements',
    success: 'Mouvement enregistré.',
    form: {
      title: 'Enregistrer un mouvement',
      fields: [
        { key: 'type_mouvement', label: 'Type', type: 'select', required: true, options: [{ value: 'entree', label: 'Entrée' }, { value: 'sortie', label: 'Sortie' }] },
        { key: 'produit_id', label: 'Produit', type: 'select', required: true, optionsFrom: { endpoint: '/magasinier/stocks', value: 'id', label: (r) => `${r.nom_produit} (${r.quantite} ${r.unite})` } },
        { key: 'stock_id', label: 'Entrepôt', type: 'select', required: true, optionsFrom: { endpoint: '/magasinier/entrepots', value: 'id', label: 'nom_stock' } },
        { key: 'quantite', label: 'Quantité', type: 'number', required: true },
        { key: 'date_mouvement', label: 'Date', type: 'date', default: today() },
        { key: 'motif', label: 'Motif', type: 'text' },
      ],
    },
  },
};

// ======================================================================
//  LOGISTIQUE (magasinier + commercial)
// ======================================================================
const collecteFields: Field[] = [
  { key: 'membre_id', label: 'Producteur', type: 'select', required: true, optionsFrom: logistiqueOption('membres', (r) => `${r.nom}${r.localite ? ' — ' + r.localite : ''}`) },
  { key: 'lieu_collecte', label: 'Lieu de collecte', type: 'text', required: true },
  { key: 'date_prevue', label: 'Date prévue', type: 'date', required: true, default: today() },
  { key: 'quantite_prevue', label: 'Quantité prévue (kg)', type: 'number', required: true },
  { key: 'produit_id', label: 'Produit concerné', type: 'select', emptyLabel: '— Non précisé —', optionsFrom: logistiqueOption('produits', 'nom_produit') },
  { key: 'stock_id', label: 'Entrepôt de destination', type: 'select', emptyLabel: '— Non précisé —', optionsFrom: logistiqueOption('entrepots', 'nom_stock') },
  { key: 'vehicule_id', label: 'Véhicule', type: 'select', emptyLabel: '— À affecter —', optionsFrom: logistiqueOption('vehicules', (r) => `${r.immatriculation}${r.type ? ' (' + r.type + ')' : ''}${r.disponible ? '' : ' — en mission'}`) },
  { key: 'chauffeur_id', label: 'Chauffeur', type: 'select', emptyLabel: '— À affecter —', optionsFrom: logistiqueOption('chauffeurs', (r) => `${nomComplet(r)}${r.disponible ? '' : ' — en mission'}`) },
  { key: 'observation', label: 'Observation', type: 'textarea' },
];

const collecteActions: RowAction[] = [
  { label: 'Démarrer', kind: 'call', method: 'post', url: '/logistique/collectes/{id}/statut', body: { statut: 'en_cours' }, style: 'primary', when: (r) => r.statut === 'planifiee', success: 'Collecte démarrée.' },
  {
    label: 'Terminer',
    kind: 'form',
    method: 'post',
    url: '/logistique/collectes/{id}/statut',
    body: { statut: 'terminee' },
    style: 'primary',
    when: (r) => r.statut === 'en_cours',
    success: 'Collecte terminée.',
    form: { title: 'Terminer la collecte', submitLabel: 'Terminer', fields: [{ key: 'quantite_collectee', label: 'Quantité réellement collectée (kg)', type: 'number', required: true }, { key: 'observation', label: 'Observation', type: 'textarea' }] },
  },
  { label: 'Annuler', kind: 'call', method: 'post', url: '/logistique/collectes/{id}/statut', body: { statut: 'annulee' }, style: 'danger', confirm: 'Annuler cette collecte ?', when: (r) => r.statut === 'planifiee' || r.statut === 'en_cours', success: 'Collecte annulée.' },
];

export const LOG_COLLECTES: PageConfig = {
  title: 'Collectes',
  subtitle: 'Planification du ramassage des récoltes chez les producteurs.',
  endpoint: '/logistique/collectes',
  emptyText: 'Aucune collecte planifiée.',
  columns: colsCollectes,
  create: {
    label: 'Planifier une collecte',
    endpoint: '/logistique/collectes',
    success: 'Collecte planifiée.',
    form: { title: 'Planifier une collecte', fields: collecteFields, submitLabel: 'Planifier' },
  },
  actions: collecteActions,
};

const livraisonStatut = (label: string, statut: string, when: (r: any) => boolean, style: 'primary' | 'danger' | 'ghost' = 'ghost', confirm?: string): RowAction => ({
  label,
  kind: 'call',
  method: 'post',
  url: '/logistique/livraisons/{id}/statut',
  body: { statut },
  style,
  confirm,
  when,
  success: `Livraison : ${label.toLowerCase()}.`,
});

export const LOG_LIVRAISONS: PageConfig = {
  title: 'Livraisons',
  subtitle: 'Expédition des commandes validées : planification, affectation et suivi.',
  endpoint: '/logistique/livraisons',
  emptyText: 'Aucune livraison planifiée.',
  columns: colsLivraisons,
  create: {
    label: 'Planifier une livraison',
    endpoint: '/logistique/livraisons',
    success: 'Livraison planifiée.',
    form: {
      title: 'Planifier une livraison',
      submitLabel: 'Planifier',
      fields: [
        { key: 'commande_id', label: 'Commande validée à livrer', type: 'select', required: true, optionsFrom: logistiqueOption('commandes', 'libelle') },
        { key: 'date_prevue', label: 'Date prévue', type: 'date', required: true, default: today() },
        { key: 'adresse_livraison', label: "Adresse de livraison (par défaut : celle du client)", type: 'text' },
        { key: 'vehicule_id', label: 'Véhicule', type: 'select', emptyLabel: '— À affecter —', optionsFrom: logistiqueOption('vehicules', (r) => `${r.immatriculation}${r.type ? ' (' + r.type + ')' : ''}${r.disponible ? '' : ' — en mission'}`) },
        { key: 'chauffeur_id', label: 'Chauffeur', type: 'select', emptyLabel: '— À affecter —', optionsFrom: logistiqueOption('chauffeurs', (r) => `${nomComplet(r)}${r.disponible ? '' : ' — en mission'}`) },
        { key: 'observation', label: 'Observation', type: 'textarea' },
      ],
    },
  },
  actions: [
    livraisonStatut('Préparer', 'en_preparation', (r) => r.statut === 'planifiee'),
    livraisonStatut('Départ', 'en_transit', (r) => r.statut === 'planifiee' || r.statut === 'en_preparation', 'primary'),
    livraisonStatut('Livrée', 'livree', (r) => r.statut === 'en_transit', 'primary'),
    livraisonStatut('Annuler', 'annulee', (r) => ['planifiee', 'en_preparation', 'en_transit'].includes(r.statut), 'danger', 'Annuler cette livraison ?'),
  ],
};

export const LOG_VEHICULES: PageConfig = {
  title: 'Véhicules',
  subtitle: 'Parc de véhicules de la coopérative.',
  endpoint: '/logistique/vehicules',
  columns: [
    { key: 'immatriculation', label: 'Immatriculation' },
    { key: 'type', label: 'Type' },
    { key: 'capacite_kg', label: 'Capacité (kg)', type: 'number' },
    { key: 'disponible', label: 'Disponibilité', type: 'badge' },
  ],
  create: {
    label: 'Ajouter un véhicule',
    endpoint: '/logistique/vehicules',
    success: 'Véhicule ajouté.',
    form: {
      title: 'Ajouter un véhicule',
      fields: [
        { key: 'immatriculation', label: 'Immatriculation', type: 'text', required: true },
        { key: 'type', label: 'Type', type: 'text', placeholder: 'Camion, camionnette…' },
        { key: 'capacite_kg', label: 'Capacité (kg)', type: 'number' },
      ],
    },
  },
  actions: [{ label: 'Supprimer', kind: 'call', method: 'delete', url: '/logistique/vehicules/{id}', style: 'danger', confirm: 'Supprimer ce véhicule ?', success: 'Véhicule supprimé.' }],
};

export const LOG_CHAUFFEURS: PageConfig = {
  title: 'Chauffeurs',
  subtitle: 'Livreurs et chauffeurs de la coopérative.',
  endpoint: '/logistique/chauffeurs',
  columns: [
    { key: 'prenom', label: 'Prénom' },
    { key: 'nom', label: 'Nom' },
    { key: 'telephone', label: 'Téléphone' },
    { key: 'numero_permis', label: 'N° de permis' },
    { key: 'disponible', label: 'Disponibilité', type: 'badge' },
  ],
  create: {
    label: 'Ajouter un chauffeur',
    endpoint: '/logistique/chauffeurs',
    success: 'Chauffeur ajouté.',
    form: {
      title: 'Ajouter un chauffeur',
      fields: [
        { key: 'prenom', label: 'Prénom', type: 'text' },
        { key: 'nom', label: 'Nom', type: 'text', required: true },
        { key: 'telephone', label: 'Téléphone', type: 'tel' },
        { key: 'numero_permis', label: 'N° de permis', type: 'text' },
      ],
    },
  },
  actions: [{ label: 'Supprimer', kind: 'call', method: 'delete', url: '/logistique/chauffeurs/{id}', style: 'danger', confirm: 'Supprimer ce chauffeur ?', success: 'Chauffeur supprimé.' }],
};

// ======================================================================
//  COMMERCIAL
// ======================================================================
export const COM_CLIENTS: PageConfig = {
  title: 'Clients',
  subtitle: 'Les clients de la coopérative (ils ne se connectent pas à la plateforme).',
  endpoint: '/commercial/clients',
  columns: [
    { key: 'nom_client', label: 'Nom' },
    { key: 'prenom_client', label: 'Prénom' },
    { key: 'telephone', label: 'Téléphone' },
    { key: 'adresse', label: 'Adresse' },
    { key: 'commandes_count', label: 'Commandes', type: 'number' },
  ],
  create: {
    label: 'Ajouter un client',
    endpoint: '/commercial/clients',
    success: 'Client ajouté.',
    form: {
      title: 'Ajouter un client',
      fields: [
        { key: 'nom_client', label: 'Nom', type: 'text', required: true },
        { key: 'prenom_client', label: 'Prénom', type: 'text' },
        { key: 'telephone', label: 'Téléphone', type: 'tel' },
        { key: 'adresse', label: 'Adresse', type: 'text' },
      ],
    },
  },
};

export const COM_COMMANDES: PageConfig = {
  title: 'Commandes',
  subtitle: 'Valider une commande déclenche la sortie de stock correspondante.',
  endpoint: '/commercial/commandes',
  columns: colsCommandes,
  createLink: { label: 'Nouvelle commande', to: '/commercial/commandes/nouvelle' },
  actions: [
    { label: 'Valider', kind: 'call', method: 'post', url: '/commercial/commandes/{id}/valider', style: 'primary', confirm: 'Valider cette commande ? Les quantités sortiront du stock.', when: (r) => r.statut === 'en_attente', success: 'Commande validée, stock mis à jour.' },
    { label: 'Refuser', kind: 'call', method: 'post', url: '/commercial/commandes/{id}/refuser', style: 'danger', confirm: 'Refuser cette commande ?', when: (r) => r.statut === 'en_attente', success: 'Commande refusée.' },
  ],
};

export const COM_VENTES: PageConfig = {
  title: 'Ventes',
  subtitle: 'Historique des commandes validées.',
  endpoint: '/commercial/ventes',
  dataPath: 'ventes',
  columns: colsCommandes.filter((c) => c.key !== 'statut'),
  indicators: (r: any): Kpi[] => [
    { label: "Chiffre d'affaires", value: fmtMoney(r.total) },
    { label: 'Nombre de ventes', value: fmtNumber(r.ventes?.length ?? 0) },
  ],
};
