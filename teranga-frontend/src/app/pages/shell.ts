import { Component, computed, inject, signal } from '@angular/core';
import { Router, RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';
import { AuthService } from '../core/auth.service';
import { ROLE_LABEL } from '../core/models';
import { NAV } from '../core/nav';
import { Logo } from '../shared/logo';

/** Cadre de l'application : menu latéral selon le rôle + zone de contenu. */
@Component({
  selector: 'app-shell',
  imports: [RouterOutlet, RouterLink, RouterLinkActive, Logo],
  template: `
    <div class="shell" [class.nav-open]="open()">
      <aside class="sidebar">
        <div class="sidebar-brand"><app-logo [size]="38" /></div>

        <nav (click)="open.set(false)">
          @for (item of nav(); track $index) {
            @if (item.section) {
              <div class="nav-section">{{ item.section }}</div>
            } @else {
              <a [routerLink]="item.to" routerLinkActive="active" [routerLinkActiveOptions]="{ exact: item.to === '/dashboard' }">{{ item.label }}</a>
            }
          }
        </nav>
      </aside>

      <div class="main">
        <header class="topbar">
          <button class="icon-btn menu-btn" (click)="open.set(!open())" aria-label="Menu">☰</button>
          <div class="spacer"></div>
          <div class="user-chip">
            <div class="avatar">{{ initials() }}</div>
            <div class="user-meta">
              <strong>{{ fullName() }}</strong>
              <small>{{ roleLabel() }}</small>
            </div>
          </div>
          <button class="btn ghost small" (click)="logout()">Déconnexion</button>
        </header>

        <main class="content"><router-outlet /></main>
      </div>

      @if (open()) {
        <div class="backdrop" (click)="open.set(false)"></div>
      }
    </div>
  `,
})
export class Shell {
  private auth = inject(AuthService);
  private router = inject(Router);

  open = signal(false);
  nav = computed(() => {
    const role = this.auth.user()?.role;
    return role ? NAV[role] : [];
  });
  fullName = computed(() => {
    const u = this.auth.user();
    return u ? `${u.prenom} ${u.nom}` : '';
  });
  initials = computed(() => {
    const u = this.auth.user();
    return u ? `${u.prenom[0] ?? ''}${u.nom[0] ?? ''}`.toUpperCase() : '';
  });
  roleLabel = computed(() => {
    const role = this.auth.user()?.role;
    return role ? ROLE_LABEL[role] : '';
  });

  async logout(): Promise<void> {
    await this.auth.logout();
    await this.router.navigate(['/login']);
  }
}
