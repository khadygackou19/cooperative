import { Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { ApiService } from '../core/api.service';
import { AuthService } from '../core/auth.service';
import { Logo } from '../shared/logo';

@Component({
  selector: 'app-login',
  imports: [FormsModule, RouterLink, Logo],
  template: `
    <main class="auth-page">
      <section class="auth-card">
        <div class="auth-brand"><app-logo [size]="52" /></div>
        <h1>Connexion</h1>
        <p class="muted">Accédez à votre espace de la coopérative.</p>

        <form (ngSubmit)="submit()" class="stack">
          <label class="field">
            <span>Identifiant</span>
            <input name="username" [ngModel]="username()" (ngModelChange)="username.set($event)" autocomplete="username" required autofocus />
          </label>
          <label class="field">
            <span>Mot de passe</span>
            <input type="password" name="password" [ngModel]="password()" (ngModelChange)="password.set($event)" autocomplete="current-password" required />
          </label>

          @if (error()) {
            <p class="alert error">{{ error() }}</p>
          }

          <button class="btn primary block" type="submit" [disabled]="busy()">{{ busy() ? 'Connexion…' : 'Se connecter' }}</button>
        </form>

        <p class="auth-foot">Vous êtes agriculteur ? <a routerLink="/inscription">Demander mon adhésion</a></p>
      </section>
    </main>
  `,
})
export class LoginPage {
  private auth = inject(AuthService);
  private router = inject(Router);

  username = signal('');
  password = signal('');
  error = signal('');
  busy = signal(false);

  async submit(): Promise<void> {
    this.error.set('');
    if (!this.username().trim() || !this.password()) {
      this.error.set('Saisissez votre identifiant et votre mot de passe.');
      return;
    }
    this.busy.set(true);
    try {
      await this.auth.login(this.username().trim(), this.password());
      await this.router.navigate(['/dashboard']);
    } catch (e) {
      this.error.set(ApiService.message(e));
    } finally {
      this.busy.set(false);
    }
  }
}
