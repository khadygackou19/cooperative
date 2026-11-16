import { Component, inject, signal } from '@angular/core';
import { ApiService } from '../core/api.service';

@Component({
  selector: 'app-rapports',
  template: `
    <header class="page-head">
      <div>
        <h1>Rapports</h1>
        <p class="muted">Documents PDF générés à partir des données de la coopérative.</p>
      </div>
    </header>

    @if (error()) {
      <p class="alert error">{{ error() }}</p>
    }

    <section class="card report">
      <div>
        <h2>Rapport de synthèse</h2>
        <p class="muted">État des stocks, ventes réalisées avec chiffre d'affaires et liste des livraisons.</p>
      </div>
      <button class="btn primary" (click)="download()" [disabled]="busy()">{{ busy() ? 'Génération…' : 'Télécharger le PDF' }}</button>
    </section>
  `,
})
export class RapportsPage {
  private api = inject(ApiService);

  busy = signal(false);
  error = signal('');

  async download(): Promise<void> {
    this.error.set('');
    this.busy.set(true);
    try {
      const blob = await this.api.blob('/admin/rapports/synthese');
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = 'rapport-synthese-teranga-agri-coop.pdf';
      a.click();
      URL.revokeObjectURL(url);
    } catch (e) {
      this.error.set(
        (e as any)?.status === 500
          ? "La génération a échoué. Vérifiez que le package barryvdh/laravel-dompdf est installé côté Laravel."
          : ApiService.message(e),
      );
    } finally {
      this.busy.set(false);
    }
  }
}
