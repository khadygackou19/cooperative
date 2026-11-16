import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { Role } from './models';
import { AuthService } from './auth.service';

export const authGuard: CanActivateFn = () => {
  const auth = inject(AuthService);
  return auth.isLoggedIn() ? true : inject(Router).createUrlTree(['/login']);
};

export const guestGuard: CanActivateFn = () => {
  const auth = inject(AuthService);
  return auth.isLoggedIn() ? inject(Router).createUrlTree(['/dashboard']) : true;
};

/** Réserve une zone de l'application à un rôle. */
export const roleGuard =
  (role: Role): CanActivateFn =>
  () => {
    const auth = inject(AuthService);
    return auth.user()?.role === role ? true : inject(Router).createUrlTree(['/dashboard']);
  };
