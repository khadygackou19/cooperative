import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { firstValueFrom } from 'rxjs';
import { API_URL } from './config';

@Injectable({ providedIn: 'root' })
export class ApiService {
  private http = inject(HttpClient);

  get<T = any>(path: string, params?: Record<string, string>): Promise<T> {
    return firstValueFrom(this.http.get<T>(API_URL + path, { params: new HttpParams({ fromObject: params ?? {} }) }));
  }

  post<T = any>(path: string, body: unknown = {}): Promise<T> {
    return firstValueFrom(this.http.post<T>(API_URL + path, body));
  }

  put<T = any>(path: string, body: unknown = {}): Promise<T> {
    return firstValueFrom(this.http.put<T>(API_URL + path, body));
  }

  delete<T = any>(path: string): Promise<T> {
    return firstValueFrom(this.http.delete<T>(API_URL + path));
  }

  blob(path: string): Promise<Blob> {
    return firstValueFrom(this.http.get(API_URL + path, { responseType: 'blob' }));
  }

  /** Transforme une erreur HTTP (Laravel) en message lisible. */
  static message(err: any): string {
    if (err?.status === 0) return "Impossible de joindre le serveur. Vérifiez que l'API est démarrée.";
    const errors = err?.error?.errors;
    if (errors && typeof errors === 'object') {
      return Object.values(errors).flat().join(' ');
    }
    return err?.error?.message || 'Une erreur est survenue.';
  }
}
