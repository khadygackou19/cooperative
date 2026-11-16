export type Role = 'admin' | 'commercial' | 'magasinier' | 'membre';

export interface User {
  id: number;
  nom: string;
  prenom: string;
  username: string;
  telephone: string | null;
  matricule: string | null;
  role: Role;
  statut: string | null;
  date_adhesion: string | null;
  localite: string | null;
  membre_id: number | null;
}

export const ROLE_LABEL: Record<Role, string> = {
  admin: 'Administrateur',
  commercial: 'Commercial',
  magasinier: 'Magasinier',
  membre: 'Membre producteur',
};

/** Libellés français des statuts renvoyés par l'API. */
export const STATUS_LABEL: Record<string, string> = {
  en_attente: 'En attente',
  validee: 'Validée',
  refusee: 'Refusée',
  actif: 'Actif',
  refuse: 'Refusé',
  planifiee: 'Planifiée',
  en_preparation: 'En préparation',
  en_transit: 'En transit',
  livree: 'Livrée',
  annulee: 'Annulée',
  en_cours: 'En cours',
  terminee: 'Terminée',
  recoltee: 'Récoltée',
  rupture: 'Rupture',
  faible: 'Niveau faible',
  normal: 'Normal',
  entree: 'Entrée',
  sortie: 'Sortie',
  admin: 'Administrateur',
  commercial: 'Commercial',
  magasinier: 'Magasinier',
  membre: 'Membre',
  true: 'Disponible',
  false: 'En mission',
};

/** Couleur du badge selon le statut. */
export function badgeClass(value: unknown): string {
  const v = String(value);
  if (['validee', 'actif', 'livree', 'terminee', 'normal', 'entree', 'true', 'recoltee'].includes(v)) return 'ok';
  if (['refusee', 'refuse', 'annulee', 'rupture', 'false'].includes(v)) return 'bad';
  if (['en_attente', 'faible', 'sortie'].includes(v)) return 'warn';
  return 'info'; // planifiee, en_preparation, en_transit, en_cours, ...
}

export interface Option {
  value: string | number | boolean;
  label: string;
}

export type FieldType = 'text' | 'number' | 'date' | 'select' | 'password' | 'textarea' | 'tel';

export interface Field {
  key: string;
  label: string;
  type: FieldType;
  required?: boolean;
  options?: Option[];
  /** Charge les options d'un select depuis l'API. */
  optionsFrom?: {
    endpoint: string;
    path?: string;
    value: string;
    label: string | ((row: any) => string);
    filter?: (row: any) => boolean;
  };
  default?: string | number;
  step?: string;
  placeholder?: string;
  /** Option vide ("Aucun") autorisée pour un select facultatif. */
  emptyLabel?: string;
}

export interface Col {
  key: string;
  label: string;
  type?: 'text' | 'money' | 'number' | 'qty' | 'date' | 'badge' | 'lignes';
  unitKey?: string;
}

export interface FormDef {
  title: string;
  fields: Field[];
  submitLabel?: string;
}

export interface RowAction {
  label: string;
  kind: 'call' | 'form' | 'link';
  method?: 'post' | 'put' | 'delete';
  /** Le jeton {id} est remplacé par l'identifiant de la ligne. */
  url?: string;
  body?: Record<string, unknown>;
  confirm?: string;
  style?: 'primary' | 'danger' | 'ghost';
  when?: (row: any) => boolean;
  form?: FormDef;
  link?: string;
  success?: string;
}

export interface Kpi {
  label: string;
  value: string;
}

export interface PageConfig {
  title: string;
  subtitle?: string;
  endpoint: string;
  dataPath?: string;
  emptyText?: string;
  columns: Col[];
  searchable?: boolean;
  indicators?: (response: any) => Kpi[];
  /** Formulaire de création ouvert dans une fenêtre. */
  create?: { label: string; endpoint: string; form: FormDef; success?: string };
  /** Ou bouton menant vers une page dédiée. */
  createLink?: { label: string; to: string };
  actions?: RowAction[];
}

export interface NavItem {
  label?: string;
  to?: string;
  section?: string;
}
