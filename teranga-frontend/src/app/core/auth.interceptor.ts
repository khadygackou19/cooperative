import { HttpErrorResponse, HttpInterceptorFn } from '@angular/common/http';
import { inject } from '@angular/core';
import { Router } from '@angular/router';
import { catchError, throwError } from 'rxjs';
import { AuthService } from './auth.service';

/** Ajoute le jeton Bearer et renvoie vers /login si le jeton n'est plus valide. */
export const authInterceptor: HttpInterceptorFn = (req, next) => {
  const auth = inject(AuthService);
  const router = inject(Router);
  const token = auth.token();

  const authReq = token ? req.clone({ setHeaders: { Authorization: `Bearer ${token}`, Accept: 'application/json' } }) : req.clone({ setHeaders: { Accept: 'application/json' } });
  const isAuthCall = req.url.endsWith('/login') || req.url.endsWith('/register');

  return next(authReq).pipe(
    catchError((err: HttpErrorResponse) => {
      if (err.status === 401 && !isAuthCall) {
        auth.clear();
        router.navigate(['/login']);
      }
      return throwError(() => err);
    }),
  );
};
