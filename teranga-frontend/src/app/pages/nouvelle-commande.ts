import { Component, OnInit, computed, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { ApiService } from '../core/api.service';
import { fmtMoney, fmtNumber } from '../core/format';
import { Icon } from '../shared/icon';

interface Ligne {
  produit_id: number | '';
  quantite: number | '';
  prix_unitaire: number | '';
}

/** Saisie d'une commande client avec plusieurs lignes de produits. */
@Component({
  selector: 'app-nouvelle-commande',
  imports: [FormsModule, RouterLink, Icon],
  template: `
    <a class="back" routerLink="/commercial/commandes"><app-icon name="arrow-left" />Retour aux commandes</a>

    <header class="page-head">
      <div>
        <h1>Nouvelle commande</h1>
        <p class="muted">Choisissez le client puis ajoutez les produits disponibles en stock.</p>
      </div>
    </header>

    @if (error()) {
      <p class="alert error"><app-icon name="triangle-alert" />{{ error() }}</p>
    }

    <form class="card" (ngSubmit)="submit()">
      <div class="form-grid">
        <label class="field">
          <span>Client <i class="req">*</i></span>
          <select name="client" [ngModel]="clientId()" (ngModelChange)="clientId.set($event)">
            <option value="">— Choisir —</option>
            @for (c of clients(); track c.id) {
              <option [value]="c.id">{{ c.prenom_client }} {{ c.nom_client }}</option>
            }
          </select>
        </label>
        <label class="field">
          <span>Date de la commande</span>
          <input type="date" name="date" [ngModel]="date()" (ngModelChange)="date.set($event)" />
        </label>
      </div>

      <h2>Produits</h2>
      @if (!produits().length && !loading()) {
        <p class="alert warn"><app-icon name="triangle-alert" />Aucun produit disponible en stock pour le moment.</p>
      }

      @for (l of lignes(); track $index; let i = $index) {
        <div class="line-row">
          <label class="field">
            <span>Produit</span>
            <select [name]="'p' + i" [ngModel]="l.produit_id" (ngModelChange)="setProduit(i, $event)">
              <option value="">— Choisir —</option>
              @for (p of produits(); track p.id) {
                <option [value]="p.id">{{ p.nom_produit }} ({{ fmtNumber(p.quantite) }} {{ p.unite }} dispo.)</option>
              }
            </select>
          </label>
          <label class="field">
            <span>Quantité</span>
            <input type="number" min="0" step="any" [name]="'q' + i" [ngModel]="l.quantite" (ngModelChange)="setLigne(i, 'quantite', $event)" />
          </label>
          <label class="field">
            <span>Prix unitaire (FCFA)</span>
            <input type="number" min="0" step="any" [name]="'u' + i" [ngModel]="l.prix_unitaire" (ngModelChange)="setLigne(i, 'prix_unitaire', $event)" />
          </label>
          <div class="line-total">{{ fmtMoney(sousTotal(l)) }}</div>
          <button type="button" class="icon-btn" (click)="retirer(i)" [disabled]="lignes().length === 1" aria-label="Retirer la ligne"><app-icon name="x" size="1.2rem" /></button>
        </div>
      }

      <button type="button" class="btn ghost small" (click)="ajouter()"><app-icon name="plus" />Ajouter un produit</button>

      <div class="total-bar">
        <span>Montant total</span>
        <strong>{{ fmtMoney(total()) }}</strong>
      </div>

      <div class="modal-actions">
        <a class="btn ghost" routerLink="/commercial/commandes">Annuler</a>
        <button class="btn primary" type="submit" [disabled]="busy()">{{ busy() ? 'Enregistrement…' : 'Enregistrer la commande' }}</button>
      </div>
    </form>
  `,
})
export class NouvelleCommandePage implements OnInit {
  private api = inject(ApiService);
  private router = inject(Router);

  clients = signal<any[]>([]);
  produits = signal<any[]>([]);
  clientId = signal<number | ''>('');
  date = signal(new Date().toISOString().slice(0, 10));
  lignes = signal<Ligne[]>([{ produit_id: '', quantite: '', prix_unitaire: '' }]);
  loading = signal(true);
  busy = signal(false);
  error = signal('');

  total = computed(() => this.lignes().reduce((s, l) => s + this.sousTotal(l), 0));

  fmtMoney = fmtMoney;
  fmtNumber = fmtNumber;

  async ngOnInit(): Promise<void> {
    try {
      const [c, p] = await Promise.all([this.api.get('/commercial/clients'), this.api.get('/commercial/produits-disponibles')]);
      this.clients.set(c);
      this.produits.set(p);
    } catch (e) {
      this.error.set(ApiService.message(e));
    } finally {
      this.loading.set(false);
    }
  }

  sousTotal(l: Ligne): number {
    return Number(l.quantite || 0) * Number(l.prix_unitaire || 0);
  }

  ajouter(): void {
    this.lignes.update((ls) => [...ls, { produit_id: '', quantite: '', prix_unitaire: '' }]);
  }

  retirer(i: number): void {
    this.lignes.update((ls) => ls.filter((_, idx) => idx !== i));
  }

  setLigne(i: number, key: keyof Ligne, value: any): void {
    this.lignes.update((ls) => ls.map((l, idx) => (idx === i ? { ...l, [key]: value } : l)));
  }

  /** Choisir un produit pré-remplit son prix de vente. */
  setProduit(i: number, id: string): void {
    const p = this.produits().find((x) => String(x.id) === String(id));
    this.lignes.update((ls) =>
      ls.map((l, idx) => (idx === i ? { ...l, produit_id: id ? Number(id) : '', prix_unitaire: p ? p.prix_vente : '' } : l)),
    );
  }

  async submit(): Promise<void> {
    this.error.set('');
    if (!this.clientId()) {
      this.error.set('Choisissez un client.');
      return;
    }
    const lignes = this.lignes();
    if (lignes.some((l) => !l.produit_id || !(Number(l.quantite) > 0))) {
      this.error.set('Chaque ligne doit avoir un produit et une quantité supérieure à 0.');
      return;
    }
    const ids = lignes.map((l) => l.produit_id);
    if (new Set(ids).size !== ids.length) {
      this.error.set('Un même produit ne peut apparaître que sur une seule ligne.');
      return;
    }

    this.busy.set(true);
    try {
      await this.api.post('/commercial/commandes', {
        client_id: Number(this.clientId()),
        date_commande: this.date(),
        lignes: lignes.map((l) => ({
          produit_id: l.produit_id,
          quantite: Number(l.quantite),
          prix_unitaire: l.prix_unitaire === '' ? null : Number(l.prix_unitaire),
        })),
      });
      await this.router.navigate(['/commercial/commandes']);
    } catch (e) {
      this.error.set(ApiService.message(e));
    } finally {
      this.busy.set(false);
    }
  }
}
