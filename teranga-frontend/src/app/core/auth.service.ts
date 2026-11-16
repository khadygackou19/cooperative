import { Injectable, computed, inject, signal } from '@angular/core';
import { ApiService } from './api.service';
import { User } from './models';

const TOKEN_KEY = 'teranga_token';
const USER_KEY = 'teranga_user';

@Injectable({ providedIn: 'root' })
export class AuthService {
  private api = inject(ApiService);

  readonly user = signal<User | null>(this.loadUser());
  readonly isLoggedIn = computed(() => !!this.user() && !!this.token());

  token(): string | null {
    try {
      return localStorage.getItem(TOKEN_KEY);
    } catch {
      return null;
    }
  }

  private loadUser(): User | null {
    try {
      const raw = localStorage.getItem(USER_KEY);
      return raw ? (JSON.parse(raw) as User) : null;
    } catch {
      return null;
    }
  }

  private store(token: string | null, user: User | null): void {
    try {
      if (token && user) {
        localStorage.setItem(TOKEN_KEY, token);
        localStorage.setItem(USER_KEY, JSON.stringify(user));
      } else {
        localStorage.removeItem(TOKEN_KEY);
        localStorage.removeItem(USER_KEY);
      }
    } catch {
      /* stockage indisponible : la session ne survivra pas au rechargement */
    }
  }

  async login(username: string, password: string): Promise<void> {
    const res = await this.api.post<{ token: string; user: User }>('/login', { username, password });
    this.store(res.token, res.user);
    this.user.set(res.user);
  }

  register(payload: Record<string, unknown>): Promise<{ message: string }> {
    return this.api.post('/register', payload);
  }

  async logout(): Promise<void> {
    try {
      await this.api.post('/logout');
    } catch {
      /* le jeton est peut-être déjà invalide */
    }
    this.clear();
  }

  /** Efface la session locale (déconnexion ou jeton expiré). */
  clear(): void {
    this.store(null, null);
    this.user.set(null);
  }

  setUser(user: User): void {
    const token = this.token();
    this.store(token, user);
    this.user.set(user);
  }
}
