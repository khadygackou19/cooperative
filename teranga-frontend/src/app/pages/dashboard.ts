import { Component, OnInit, computed, inject, signal } from '@angular/core';
import { ApiService } from '../core/api.service';
import { AuthService } from '../core/auth.service';
import { fmtDate, fmtMoney, fmtNumber } from '../core/format';
import { STATUS_LABEL, badgeClass } from '../core/models';
import { Icon } from '../shared/icon';

type Kind = 'n' | 'money' | 'kg';
interface KpiMeta {
  label: string;
  kind: Kind;
  tone?: 'warn' | 'bad';
}

const KPI: Record<string, KpiMeta> = {
  membres_actifs: { label: 'Membres actifs', kind: 'n' },
  adhesions_en_attente: { label: "Demandes d'adhésion en attente", kind: 'n', tone: 'warn' },
  rendement_global: { label: 'Rendement global (reçu en stock)', kind: 'kg' },
  stock_total: { label: 'Niveau de stock', kind: 'kg' },
  produits_en_rupture: { label: 'Produits en rupture', kind: 'n', tone: 'bad' },
  produits_niveau_faible: { label: 'Produits à niveau faible', kind: 'n', tone: 'warn' },
  revenus: { label: 'Revenus', kind: 'money' },
  commandes_en_attente: { label: 'Commandes en attente', kind: 'n', tone: 'warn' },
  livraisons_en_cours: { label: 'Livraisons en cours', kind: 'n' },
  collectes_a_venir: { label: 'Collectes à venir', kind: 'n' },
  parcelles: { label: 'Parcelles', kind: 'n' },
  superficie_totale: { label: 'Superficie totale (ha)', kind: 'n' },
  productions_declarees: { label: 'Productions déclarées', kind: 'n' },
  productions_validees: { label: 'Productions validées', kind: 'n' },
  productions_en_attente: { label: 'Productions en attente', kind: 'n', tone: 'warn' },
  quantite_produite: { label: 'Quantité produite', kind: 'kg' },
  collectes_prevues: { label: 'Collectes prévues', kind: 'n' },
  produits_en_stock: { label: 'Produits en stock', kind: 'n' },
  livraisons_a_preparer: { label: 'Livraisons à préparer', kind: 'n' },
  commandes_validees: { label: 'Commandes validées', kind: 'n' },
  commandes_refusees: { label: 'Commandes refusées', kind: 'n' },
  chiffre_affaires: { label: "Chiffre d'affaires", kind: 'money' },
  commandes_a_livrer: { label: 'Commandes à livrer', kind: 'n', tone: 'warn' },
};

const ICON: Record<string, string> = {
  membres_actifs: 'users', adhesions_en_attente: 'user-plus', rendement_global: 'wheat', stock_total: 'warehouse',
  produits_en_rupture: 'package-x', produits_niveau_faible: 'triangle-alert', revenus: 'hand-coins',
  commandes_en_attente: 'clock', livraisons_en_cours: 'truck', collectes_a_venir: 'tractor', parcelles: 'map-pin',
  superficie_totale: 'layers', productions_declarees: 'clipboard-list', productions_validees: 'circle-check',
  productions_en_attente: 'clock', quantite_produite: 'scale', collectes_prevues: 'tractor', produits_en_stock: 'boxes',
  livraisons_a_preparer: 'package', commandes_validees: 'circle-check', commandes_refusees: 'circle-x',
  chiffre_affaires: 'hand-coins', commandes_a_livrer: 'truck',
};

@Component({
  selector: 'app-dashboard',
  imports: [Icon],
  template: `
    <section class="greeting">
      <div>
        <h1>Bonjour {{ prenom() }}</h1>
        <p>Voici où en est la coopérative aujourd'hui.</p>
      </div>
      <span class="greeting-date"><app-icon name="calendar-days" />{{ today }}</span>
    </section>

    @if (error()) {
      <p class="alert error"><app-icon name="triangle-alert" />{{ error() }}</p>
    } @else if (loading()) {
      <p class="muted">Chargement…</p>
    } @else {
      <section class="kpi-grid">
        @for (k of cards(); track k.label) {
          <article class="kpi" [class.warn]="k.tone === 'warn'" [class.bad]="k.tone === 'bad'">
            <span class="kpi-icon"><app-icon [name]="k.icon" /></span>
            <div class="kpi-body">
              <span class="kpi-label">{{ k.label }}</span>
              <strong class="kpi-value">{{ k.value }}</strong>
            </div>
          </article>
        }
      </section>

      @if (alertes().length) {
        <section class="card">
          <h2>Alertes de stock</h2>
          <div class="table-wrap">
            <table>
              <thead><tr><th>Produit</th><th>Quantité</th><th>Niveau</th></tr></thead>
              <tbody>
                @for (a of alertes(); track a.id) {
                  <tr>
                    <td>{{ a.nom_produit }}</td>
                    <td>{{ fmtNumber(a.quantite) }} {{ a.unite }}</td>
                    <td><span class="badge" [class]="badgeClass(a.niveau)">{{ label(a.niveau) }}</span></td>
                  </tr>
                }
              </tbody>
            </table>
          </div>
        </section>
      }

      @if (activites().length) {
        <section class="card">
          <h2>Dernières activités</h2>
          <div class="table-wrap">
            <table>
              <thead><tr><th>Date</th><th>Auteur</th><th>Action</th><th>Détails</th></tr></thead>
              <tbody>
                @for (a of activites(); track a.id) {
                  <tr>
                    <td>{{ fmtDate(a.date) }}</td>
                    <td>{{ a.auteur ?? '—' }}</td>
                    <td>{{ a.action }}</td>
                    <td>{{ a.details ?? '' }}</td>
                  </tr>
                }
              </tbody>
            </table>
          </div>
        </section>
      }
    }
  `,
})
export class DashboardPage implements OnInit {
  private api = inject(ApiService);
  private auth = inject(AuthService);

  data = signal<any>(null);
  loading = signal(true);
  error = signal('');

  prenom = computed(() => this.auth.user()?.prenom ?? '');
  alertes = computed<any[]>(() => this.data()?.alertes ?? []);
  activites = computed<any[]>(() => this.data()?.activites ?? []);
  cards = computed(() => {
    const kpis: Record<string, number> = this.data()?.kpis ?? {};
    return Object.entries(kpis).map(([key, value]) => {
      const meta = KPI[key] ?? { label: key, kind: 'n' as Kind };
      const text = meta.kind === 'money' ? fmtMoney(value) : meta.kind === 'kg' ? `${fmtNumber(value)} kg` : fmtNumber(value);
      // une alerte à 0 n'est pas une alerte
      return { label: meta.label, value: text, icon: ICON[key] ?? 'chart-no-axes-column', tone: value > 0 ? meta.tone : undefined };
    });
  });

  today = new Date().toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' });
  fmtNumber = fmtNumber;
  fmtDate = fmtDate;
  badgeClass = badgeClass;
  label = (v: string) => STATUS_LABEL[v] ?? v;

  async ngOnInit(): Promise<void> {
    try {
      this.data.set(await this.api.get('/dashboard'));
    } catch (e) {
      this.error.set(ApiService.message(e));
    } finally {
      this.loading.set(false);
    }
  }
}
