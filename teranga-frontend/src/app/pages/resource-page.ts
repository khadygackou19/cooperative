import { Component, OnInit, computed, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { ApiService } from '../core/api.service';
import { fmtDate, fmtMoney, fmtNumber } from '../core/format';
import { Col, Kpi, PageConfig, RowAction, STATUS_LABEL, badgeClass } from '../core/models';
import { FormModal } from '../shared/form-modal';
import { Icon } from '../shared/icon';

/**
 * Page de liste générique : la configuration (colonnes, formulaire, actions par ligne)
 * est fournie par la route (data.config). Voir pages/page-configs.ts.
 */
@Component({
  selector: 'app-resource-page',
  imports: [FormsModule, RouterLink, FormModal, Icon],
  template: `
    <header class="page-head">
      <div>
        <h1>{{ cfg.title }}</h1>
        @if (cfg.subtitle) {
          <p class="muted">{{ cfg.subtitle }}</p>
        }
      </div>
      @if (cfg.create) {
        <button class="btn primary" (click)="showCreate.set(true)"><app-icon name="plus" />{{ cfg.create.label }}</button>
      }
      @if (cfg.createLink) {
        <a class="btn primary" [routerLink]="cfg.createLink.to"><app-icon name="plus" />{{ cfg.createLink.label }}</a>
      }
    </header>

    @if (notice()) {
      <p class="alert success"><app-icon name="circle-check" />{{ notice() }}</p>
    }
    @if (error()) {
      <p class="alert error"><app-icon name="triangle-alert" />{{ error() }}</p>
    }

    @if (kpis().length) {
      <section class="kpi-grid compact">
        @for (k of kpis(); track k.label) {
          <article class="kpi">
            <span class="kpi-label">{{ k.label }}</span>
            <strong class="kpi-value">{{ k.value }}</strong>
          </article>
        }
      </section>
    }

    <section class="card">
      @if (cfg.searchable !== false && rows().length > 5) {
        <label class="search-box">
          <app-icon name="search" />
          <input type="search" placeholder="Rechercher dans la liste" aria-label="Rechercher" [ngModel]="search()" (ngModelChange)="search.set($event)" />
        </label>
      }

      @if (loading()) {
        <p class="muted pad">Chargement…</p>
      } @else if (!filtered().length) {
        <div class="empty">
          <app-icon name="package" />
          <p class="muted">{{ cfg.emptyText ?? 'Aucune donnée à afficher.' }}</p>
        </div>
      } @else {
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                @for (c of cfg.columns; track c.key) {
                  <th>{{ c.label }}</th>
                }
                @if (cfg.actions?.length) {
                  <th class="right">Actions</th>
                }
              </tr>
            </thead>
            <tbody>
              @for (row of filtered(); track row.id ?? $index) {
                <tr>
                  @for (c of cfg.columns; track c.key) {
                    <td>
                      @switch (c.type) {
                        @case ('badge') {
                          @if (row[c.key] !== null && row[c.key] !== undefined) {
                            <span class="badge" [class]="badgeClass(row[c.key])">{{ label(row[c.key]) }}</span>
                          } @else {
                            —
                          }
                        }
                        @case ('lignes') {
                          @for (l of row[c.key] ?? []; track $index) {
                            <div class="line">{{ l.produit }} × {{ fmtNumber(l.quantite) }} {{ l.unite }}</div>
                          }
                        }
                        @default {
                          {{ cell(row, c) }}
                        }
                      }
                    </td>
                  }
                  @if (cfg.actions?.length) {
                    <td class="right nowrap">
                      @for (a of cfg.actions!; track a.label) {
                        @if (!a.when || a.when(row)) {
                          <button class="btn small" [class]="a.style ?? 'ghost'" [disabled]="busy()" (click)="run(a, row)">{{ a.label }}</button>
                        }
                      }
                    </td>
                  }
                </tr>
              }
            </tbody>
          </table>
        </div>
      }
    </section>

    @if (showCreate() && cfg.create) {
      <app-form-modal
        [title]="cfg.create.form.title"
        [fields]="cfg.create.form.fields"
        [submitLabel]="cfg.create.form.submitLabel ?? 'Enregistrer'"
        [handler]="createHandler"
        (closed)="showCreate.set(false)"
      />
    }

    @if (active(); as act) {
      <app-form-modal
        [title]="act.action.form!.title"
        [fields]="act.action.form!.fields"
        [submitLabel]="act.action.form!.submitLabel ?? 'Confirmer'"
        [handler]="actionHandler(act.action, act.row)"
        (closed)="active.set(null)"
      />
    }
  `,
})
export class ResourcePage implements OnInit {
  private route = inject(ActivatedRoute);
  private router = inject(Router);
  private api = inject(ApiService);

  cfg = this.route.snapshot.data['config'] as PageConfig;

  rows = signal<any[]>([]);
  kpis = signal<Kpi[]>([]);
  loading = signal(true);
  busy = signal(false);
  error = signal('');
  notice = signal('');
  search = signal('');
  showCreate = signal(false);
  active = signal<{ action: RowAction; row: any } | null>(null);

  filtered = computed(() => {
    const q = this.search().trim().toLowerCase();
    if (!q) return this.rows();
    return this.rows().filter((r) => JSON.stringify(Object.values(r)).toLowerCase().includes(q));
  });

  fmtNumber = fmtNumber;
  badgeClass = badgeClass;
  label = (v: unknown) => STATUS_LABEL[String(v)] ?? String(v);

  ngOnInit(): void {
    void this.load();
  }

  async load(): Promise<void> {
    try {
      const resp = await this.api.get(this.cfg.endpoint);
      this.rows.set(Array.isArray(resp) ? resp : (resp[this.cfg.dataPath ?? 'donnees'] ?? []));
      this.kpis.set(this.cfg.indicators ? this.cfg.indicators(resp) : []);
      this.error.set('');
    } catch (e) {
      this.error.set(ApiService.message(e));
    } finally {
      this.loading.set(false);
    }
  }

  cell(row: any, c: Col): string {
    const v = row[c.key];
    if (v === null || v === undefined || v === '') return '—';
    switch (c.type) {
      case 'money':
        return fmtMoney(v);
      case 'number':
        return fmtNumber(v);
      case 'qty':
        return `${fmtNumber(v)} ${c.unitKey ? (row[c.unitKey] ?? '') : ''}`.trim();
      case 'date':
        return fmtDate(v);
      default:
        return String(v);
    }
  }

  private url(a: RowAction, row: any): string {
    return (a.url ?? '').replace('{id}', String(row.id));
  }

  private async call(a: RowAction, row: any, body: Record<string, unknown> = {}): Promise<void> {
    const url = this.url(a, row);
    const method = a.method ?? 'post';
    if (method === 'delete') await this.api.delete(url);
    else if (method === 'put') await this.api.put(url, body);
    else await this.api.post(url, body);
    this.notice.set(a.success ?? 'Opération effectuée.');
    await this.load();
  }

  async run(a: RowAction, row: any): Promise<void> {
    this.notice.set('');
    this.error.set('');

    if (a.kind === 'link') {
      await this.router.navigateByUrl((a.link ?? '').replace('{id}', String(row.id)));
      return;
    }
    if (a.kind === 'form') {
      this.active.set({ action: a, row });
      return;
    }
    if (a.confirm && !window.confirm(a.confirm)) return;

    this.busy.set(true);
    try {
      await this.call(a, row, a.body ?? {});
    } catch (e) {
      this.error.set(ApiService.message(e));
    } finally {
      this.busy.set(false);
    }
  }

  actionHandler(a: RowAction, row: any): (v: Record<string, unknown>) => Promise<void> {
    return (value) => this.call(a, row, { ...(a.body ?? {}), ...value });
  }

  createHandler = async (value: Record<string, unknown>): Promise<void> => {
    await this.api.post(this.cfg.create!.endpoint, value);
    this.notice.set(this.cfg.create!.success ?? 'Enregistré avec succès.');
    await this.load();
  };
}
