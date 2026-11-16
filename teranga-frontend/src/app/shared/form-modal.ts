import { Component, OnInit, inject, input, output, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ApiService } from '../core/api.service';
import { Field, Option } from '../core/models';
import { Icon } from './icon';

/**
 * Fenêtre de formulaire générique, pilotée par une liste de champs.
 * Les listes déroulantes peuvent être alimentées depuis l'API (optionsFrom).
 */
@Component({
  selector: 'app-form-modal',
  imports: [FormsModule, Icon],
  template: `
    <div class="overlay" (click)="closed.emit()">
      <div class="modal" (click)="$event.stopPropagation()" role="dialog" [attr.aria-label]="title()">
        <div class="modal-head">
          <h2>{{ title() }}</h2>
          <button type="button" class="icon-btn" (click)="closed.emit()" aria-label="Fermer"><app-icon name="x" size="1.25rem" /></button>
        </div>

        <form (ngSubmit)="save()" class="form-grid">
          @for (f of fields(); track f.key) {
            <label class="field" [class.wide]="f.type === 'textarea'">
              <span>{{ f.label }}@if (f.required) { <i class="req">*</i> }</span>

              @switch (f.type) {
                @case ('select') {
                  <select [name]="f.key" [ngModel]="model()[f.key]" (ngModelChange)="set(f.key, $event)">
                    @if (!f.required || !options()[f.key]?.length) {
                      <option value="">{{ f.emptyLabel ?? '— Choisir —' }}</option>
                    }
                    @for (o of options()[f.key] ?? []; track o.value) {
                      <option [value]="o.value">{{ o.label }}</option>
                    }
                  </select>
                }
                @case ('textarea') {
                  <textarea rows="3" [name]="f.key" [ngModel]="model()[f.key]" (ngModelChange)="set(f.key, $event)"></textarea>
                }
                @default {
                  <input
                    [type]="f.type"
                    [name]="f.key"
                    [attr.step]="f.step ?? (f.type === 'number' ? 'any' : null)"
                    [attr.min]="f.type === 'number' ? 0 : null"
                    [placeholder]="f.placeholder ?? ''"
                    [ngModel]="model()[f.key]"
                    (ngModelChange)="set(f.key, $event)"
                    autocomplete="off"
                  />
                }
              }
            </label>
          }

          @if (error()) {
            <p class="alert error wide"><app-icon name="triangle-alert" />{{ error() }}</p>
          }

          <div class="modal-actions wide">
            <button type="button" class="btn ghost" (click)="closed.emit()">Annuler</button>
            <button type="submit" class="btn primary" [disabled]="busy()">{{ busy() ? 'Enregistrement…' : submitLabel() }}</button>
          </div>
        </form>
      </div>
    </div>
  `,
})
export class FormModal implements OnInit {
  private api = inject(ApiService);

  title = input.required<string>();
  fields = input.required<Field[]>();
  submitLabel = input('Enregistrer');
  /** Appelée à la validation ; doit lever une erreur HTTP en cas d'échec. */
  handler = input.required<(value: Record<string, unknown>) => Promise<void>>();
  closed = output<void>();

  model = signal<Record<string, any>>({});
  options = signal<Record<string, Option[]>>({});
  error = signal('');
  busy = signal(false);

  async ngOnInit(): Promise<void> {
    const initial: Record<string, any> = {};
    for (const f of this.fields()) {
      initial[f.key] = f.default ?? '';
    }
    this.model.set(initial);

    const cache = new Map<string, Promise<any>>();
    const loads = this.fields().map(async (f) => {
      if (f.type === 'select' && f.options) {
        this.options.update((o) => ({ ...o, [f.key]: f.options! }));
      }
      const src = f.optionsFrom;
      if (!src) return;
      try {
        if (!cache.has(src.endpoint)) cache.set(src.endpoint, this.api.get(src.endpoint));
        const resp = await cache.get(src.endpoint)!;
        let rows: any[] = src.path ? resp[src.path] : resp;
        if (src.filter) rows = rows.filter(src.filter);
        const opts = rows.map((r) => ({
          value: r[src.value],
          label: typeof src.label === 'function' ? src.label(r) : String(r[src.label]),
        }));
        this.options.update((o) => ({ ...o, [f.key]: opts }));
      } catch (e) {
        this.error.set(ApiService.message(e));
      }
    });
    await Promise.all(loads);
  }

  set(key: string, value: unknown): void {
    this.model.update((m) => ({ ...m, [key]: value }));
  }

  async save(): Promise<void> {
    this.error.set('');
    const payload: Record<string, unknown> = {};

    for (const f of this.fields()) {
      const raw = this.model()[f.key];
      if (f.required && (raw === '' || raw === null || raw === undefined)) {
        this.error.set(`Le champ « ${f.label} » est obligatoire.`);
        return;
      }
      if (raw === '' || raw === null || raw === undefined) continue;
      payload[f.key] = f.type === 'number' ? Number(raw) : f.type === 'select' && /^\d+$/.test(String(raw)) ? Number(raw) : raw;
    }

    this.busy.set(true);
    try {
      await this.handler()(payload);
      this.closed.emit();
    } catch (e) {
      this.error.set(ApiService.message(e));
    } finally {
      this.busy.set(false);
    }
  }
}
