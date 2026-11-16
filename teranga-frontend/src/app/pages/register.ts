import { Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { ApiService } from '../core/api.service';
import { AuthService } from '../core/auth.service';
import { Logo } from '../shared/logo';

@Component({
  selector: 'app-register',
  imports: [FormsModule, RouterLink, Logo],
  template: `
    <main class="auth-page">
      <section class="auth-card wide-card">
        <div class="auth-brand"><app-logo [size]="52" /></div>
        <h1>Demande d'adhésion</h1>
        <p class="muted">Renseignez vos informations. Un administrateur validera votre demande avant que vous puissiez accéder à votre espace.</p>

        @if (done()) {
          <p class="alert success">{{ done() }}</p>
          <a class="btn primary block" routerLink="/login">Retour à la connexion</a>
        } @else {
          <form (ngSubmit)="submit()" class="form-grid">
            <label class="field"><span>Prénom *</span><input name="prenom" [ngModel]="m()['prenom']" (ngModelChange)="set('prenom', $event)" /></label>
            <label class="field"><span>Nom *</span><input name="nom" [ngModel]="m()['nom']" (ngModelChange)="set('nom', $event)" /></label>
            <label class="field"><span>Téléphone *</span><input type="tel" name="telephone" [ngModel]="m()['telephone']" (ngModelChange)="set('telephone', $event)" /></label>
            <label class="field"><span>Localité</span><input name="localite" [ngModel]="m()['localite']" (ngModelChange)="set('localite', $event)" placeholder="Village / commune" /></label>
            <label class="field wide"><span>Identifiant de connexion *</span><input name="username" [ngModel]="m()['username']" (ngModelChange)="set('username', $event)" autocomplete="username" /></label>
            <label class="field"><span>Mot de passe * (6 caractères min.)</span><input type="password" name="password" [ngModel]="m()['password']" (ngModelChange)="set('password', $event)" autocomplete="new-password" /></label>
            <label class="field"><span>Confirmer le mot de passe *</span><input type="password" name="password_confirmation" [ngModel]="m()['password_confirmation']" (ngModelChange)="set('password_confirmation', $event)" autocomplete="new-password" /></label>

            @if (error()) {
              <p class="alert error wide">{{ error() }}</p>
            }

            <button class="btn primary block wide" type="submit" [disabled]="busy()">{{ busy() ? 'Envoi…' : 'Envoyer ma demande' }}</button>
          </form>
          <p class="auth-foot">Déjà membre ? <a routerLink="/login">Se connecter</a></p>
        }
      </section>
    </main>
  `,
})
export class RegisterPage {
  private auth = inject(AuthService);

  m = signal<Record<string, string>>({ prenom: '', nom: '', telephone: '', localite: '', username: '', password: '', password_confirmation: '' });
  error = signal('');
  done = signal('');
  busy = signal(false);

  set(key: string, value: string): void {
    this.m.update((v) => ({ ...v, [key]: value }));
  }

  async submit(): Promise<void> {
    this.error.set('');
    const v = this.m();
    if (!v['prenom'].trim() || !v['nom'].trim() || !v['telephone'].trim() || !v['username'].trim() || !v['password']) {
      this.error.set('Veuillez remplir tous les champs obligatoires (*).');
      return;
    }
    if (v['password'] !== v['password_confirmation']) {
      this.error.set('Les mots de passe ne correspondent pas.');
      return;
    }
    this.busy.set(true);
    try {
      const res = await this.auth.register(v);
      this.done.set(res.message);
    } catch (e) {
      this.error.set(ApiService.message(e));
    } finally {
      this.busy.set(false);
    }
  }
}
