import { Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ApiService } from '../core/api.service';
import { AuthService } from '../core/auth.service';
import { ROLE_LABEL, User } from '../core/models';
import { Icon } from '../shared/icon';

@Component({
  selector: 'app-profil',
  imports: [FormsModule, Icon],
  template: `
    <header class="page-head">
      <div>
        <h1>Mon profil</h1>
        <p class="muted">{{ roleLabel }} — identifiant : <strong>{{ user?.username }}</strong></p>
      </div>
    </header>

    @if (notice()) {
      <p class="alert success"><app-icon name="circle-check" />{{ notice() }}</p>
    }
    @if (error()) {
      <p class="alert error"><app-icon name="triangle-alert" />{{ error() }}</p>
    }

    <form class="card" (ngSubmit)="save()">
      <h2>Informations personnelles</h2>
      <div class="form-grid">
        <label class="field"><span>Prénom</span><input name="prenom" [ngModel]="m()['prenom']" (ngModelChange)="set('prenom', $event)" /></label>
        <label class="field"><span>Nom</span><input name="nom" [ngModel]="m()['nom']" (ngModelChange)="set('nom', $event)" /></label>
        <label class="field"><span>Téléphone</span><input type="tel" name="telephone" [ngModel]="m()['telephone']" (ngModelChange)="set('telephone', $event)" /></label>
      </div>

      <h2>Changer le mot de passe</h2>
      <p class="muted">Laissez vide pour conserver le mot de passe actuel.</p>
      <div class="form-grid">
        <label class="field"><span>Mot de passe actuel</span><input type="password" name="ancien" [ngModel]="m()['ancien_password']" (ngModelChange)="set('ancien_password', $event)" autocomplete="current-password" /></label>
        <span></span>
        <label class="field"><span>Nouveau mot de passe</span><input type="password" name="nouveau" [ngModel]="m()['password']" (ngModelChange)="set('password', $event)" autocomplete="new-password" /></label>
        <label class="field"><span>Confirmer</span><input type="password" name="confirm" [ngModel]="m()['password_confirmation']" (ngModelChange)="set('password_confirmation', $event)" autocomplete="new-password" /></label>
      </div>

      <div class="modal-actions">
        <button class="btn primary" type="submit" [disabled]="busy()">{{ busy() ? 'Enregistrement…' : 'Enregistrer' }}</button>
      </div>
    </form>
  `,
})
export class ProfilPage {
  private api = inject(ApiService);
  private auth = inject(AuthService);

  user: User | null = this.auth.user();
  roleLabel = this.user ? ROLE_LABEL[this.user.role] : '';

  m = signal<Record<string, string>>({
    prenom: this.user?.prenom ?? '',
    nom: this.user?.nom ?? '',
    telephone: this.user?.telephone ?? '',
    ancien_password: '',
    password: '',
    password_confirmation: '',
  });
  notice = signal('');
  error = signal('');
  busy = signal(false);

  set(key: string, value: string): void {
    this.m.update((v) => ({ ...v, [key]: value }));
  }

  async save(): Promise<void> {
    this.notice.set('');
    this.error.set('');
    const v = this.m();

    const body: Record<string, string> = { prenom: v['prenom'], nom: v['nom'], telephone: v['telephone'] };
    if (v['password']) {
      if (v['password'] !== v['password_confirmation']) {
        this.error.set('Les mots de passe ne correspondent pas.');
        return;
      }
      body['ancien_password'] = v['ancien_password'];
      body['password'] = v['password'];
      body['password_confirmation'] = v['password_confirmation'];
    }

    this.busy.set(true);
    try {
      const updated = await this.api.put<User>('/me', body);
      this.auth.setUser(updated);
      this.user = updated;
      this.m.update((x) => ({ ...x, ancien_password: '', password: '', password_confirmation: '' }));
      this.notice.set('Profil mis à jour.');
    } catch (e) {
      this.error.set(ApiService.message(e));
    } finally {
      this.busy.set(false);
    }
  }
}
