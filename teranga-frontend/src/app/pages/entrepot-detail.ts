import { Component, OnInit, inject, signal } from '@angular/core';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { ApiService } from '../core/api.service';
import { fmtDate, fmtNumber } from '../core/format';
import { STATUS_LABEL, badgeClass } from '../core/models';
import { Icon } from '../shared/icon';

/** Détail d'un entrepôt (bouton « Voir » de la liste des entrepôts). */
@Component({
  selector: 'app-entrepot-detail',
  imports: [RouterLink, Icon],
  template: `
    <a class="back" routerLink="/magasinier/entrepots"><app-icon name="arrow-left" />Retour aux entrepôts</a>

    @if (error()) {
      <p class="alert error"><app-icon name="triangle-alert" />{{ error() }}</p>
    } @else if (!e()) {
      <p class="muted">Chargement…</p>
    } @else {
      <header class="page-head">
        <div>
          <h1>{{ e().nom_stock }}</h1>
          <p class="muted">{{ e().adresse || 'Adresse non renseignée' }}</p>
        </div>
      </header>

      <section class="kpi-grid compact">
        <article class="kpi"><span class="kpi-label">Produits stockés</span><strong class="kpi-value">{{ e().produits.length }}</strong></article>
        <article class="kpi"><span class="kpi-label">Quantité totale</span><strong class="kpi-value">{{ fmtNumber(e().quantite_totale) }} kg</strong></article>
      </section>

      <section class="card">
        <h2>Produits en stock</h2>
        @if (!e().produits.length) {
          <p class="muted pad">Cet entrepôt est vide.</p>
        } @else {
          <div class="table-wrap">
            <table>
              <thead><tr><th>Produit</th><th>Quantité</th></tr></thead>
              <tbody>
                @for (p of e().produits; track p.produit_id) {
                  <tr><td>{{ p.nom_produit }}</td><td>{{ fmtNumber(p.quantite) }} {{ p.unite }}</td></tr>
                }
              </tbody>
            </table>
          </div>
        }
      </section>

      <section class="card">
        <h2>Derniers mouvements</h2>
        @if (!e().mouvements.length) {
          <p class="muted pad">Aucun mouvement.</p>
        } @else {
          <div class="table-wrap">
            <table>
              <thead><tr><th>Date</th><th>Type</th><th>Produit</th><th>Quantité</th><th>Motif</th></tr></thead>
              <tbody>
                @for (m of e().mouvements; track m.id) {
                  <tr>
                    <td>{{ fmtDate(m.date) }}</td>
                    <td><span class="badge" [class]="badgeClass(m.type)">{{ label(m.type) }}</span></td>
                    <td>{{ m.produit }}</td>
                    <td>{{ fmtNumber(m.quantite) }}</td>
                    <td>{{ m.motif || '—' }}</td>
                  </tr>
                }
              </tbody>
            </table>
          </div>
        }
      </section>
    }
  `,
})
export class EntrepotDetailPage implements OnInit {
  private api = inject(ApiService);
  private route = inject(ActivatedRoute);

  e = signal<any>(null);
  error = signal('');

  fmtNumber = fmtNumber;
  fmtDate = fmtDate;
  badgeClass = badgeClass;
  label = (v: string) => STATUS_LABEL[v] ?? v;

  async ngOnInit(): Promise<void> {
    try {
      this.e.set(await this.api.get(`/magasinier/entrepots/${this.route.snapshot.paramMap.get('id')}`));
    } catch (err) {
      this.error.set(ApiService.message(err));
    }
  }
}
