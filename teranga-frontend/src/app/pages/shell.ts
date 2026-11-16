import { Component, computed, inject, signal } from '@angular/core';
import { Router, RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';
import { AuthService } from '../core/auth.service';
import { ROLE_LABEL } from '../core/models';
import { NAV } from '../core/nav';
import { Icon } from '../shared/icon';
import { Logo } from '../shared/logo';

/** Cadre de l'application : menu latéral selon le rôle + zone de contenu. */
@Component({
  selector: 'app-shell',
  imports: [RouterOutlet, RouterLink, RouterLinkActive, Logo, Icon],
  template: `
    <div class="shell" [class.nav-open]="open()">
      <aside class="sidebar">
        <div class="sidebar-brand"><app-logo [size]="40" /></div>

        <nav (click)="open.set(false)">
          @for (item of nav(); track $index) {
            @if (item.section) {
              <div class="nav-section">{{ item.section }}</div>
            } @else {
              <a [routerLink]="item.to" routerLinkActive="active" [routerLinkActiveOptions]="{ exact: item.to === '/dashboard' }">
                <app-icon [name]="item.icon ?? 'chevron-right'" />
                <span>{{ item.label }}</span>
              </a>
            }
          }
        </nav>

        <div class="sidebar-user">
          <div class="avatar">{{ initials() }}</div>
          <div class="user-meta">
            <strong>{{ fullName() }}</strong>
            <small>{{ roleLabel() }}</small>
          </div>
          <button class="icon-btn" (click)="logout()" aria-label="Se déconnecter" title="Se déconnecter">
            <app-icon name="log-out" size="1.25rem" />
          </button>
        </div>
      </aside>

      <div class="main">
        <header class="topbar">
          <button class="icon-btn" (click)="open.set(!open())" aria-label="Ouvrir le menu"><app-icon name="menu" size="1.5rem" /></button>
          <app-logo [size]="30" />
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
