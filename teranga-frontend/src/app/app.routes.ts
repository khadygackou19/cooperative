import { Routes } from '@angular/router';
import { authGuard, guestGuard, roleGuard } from './core/guards';
import { DashboardPage } from './pages/dashboard';
import { EntrepotDetailPage } from './pages/entrepot-detail';
import { LoginPage } from './pages/login';
import { NouvelleCommandePage } from './pages/nouvelle-commande';
import * as P from './pages/page-configs';
import { ProfilPage } from './pages/profil';
import { RapportsPage } from './pages/rapports';
import { RegisterPage } from './pages/register';
import { ResourcePage } from './pages/resource-page';
import { Shell } from './pages/shell';
import { PageConfig } from './core/models';

/** Route de liste générique pilotée par une configuration. */
const list = (path: string, config: PageConfig) => ({ path, component: ResourcePage, data: { config } });

export const routes: Routes = [
  { path: 'login', component: LoginPage, canActivate: [guestGuard] },
  { path: 'inscription', component: RegisterPage, canActivate: [guestGuard] },

  {
    path: '',
    component: Shell,
    canActivate: [authGuard],
    children: [
      { path: '', pathMatch: 'full', redirectTo: 'dashboard' },
      { path: 'dashboard', component: DashboardPage },
      { path: 'profil', component: ProfilPage },

      {
        path: 'admin',
        canActivate: [roleGuard('admin')],
        children: [
          list('utilisateurs', P.ADMIN_UTILISATEURS),
          list('productions', P.ADMIN_PRODUCTIONS),
          list('stocks', P.ADMIN_STOCKS),
          list('commandes', P.ADMIN_COMMANDES),
          list('ventes', P.ADMIN_VENTES),
          list('livraisons', P.ADMIN_LIVRAISONS),
          list('journal', P.ADMIN_JOURNAL),
          { path: 'rapports', component: RapportsPage },
        ],
      },

      {
        path: 'membre',
        canActivate: [roleGuard('membre')],
        children: [
          list('parcelles', P.MEMBRE_PARCELLES),
          list('periodes', P.MEMBRE_PERIODES),
          list('cultures', P.MEMBRE_CULTURES),
          list('productions', P.MEMBRE_PRODUCTIONS),
          list('collectes', P.MEMBRE_COLLECTES),
        ],
      },

      {
        path: 'magasinier',
        canActivate: [roleGuard('magasinier')],
        children: [
          list('stocks', P.MAG_STOCKS),
          list('entrepots', P.MAG_ENTREPOTS),
          { path: 'entrepots/:id', component: EntrepotDetailPage },
          list('productions', P.MAG_PRODUCTIONS),
          list('mouvements', P.MAG_MOUVEMENTS),
          list('collectes', P.LOG_COLLECTES),
          list('livraisons', P.LOG_LIVRAISONS),
          list('vehicules', P.LOG_VEHICULES),
          list('chauffeurs', P.LOG_CHAUFFEURS),
        ],
      },

      {
        path: 'commercial',
        canActivate: [roleGuard('commercial')],
        children: [
          list('clients', P.COM_CLIENTS),
          list('commandes', P.COM_COMMANDES),
          { path: 'commandes/nouvelle', component: NouvelleCommandePage },
          list('ventes', P.COM_VENTES),
          list('livraisons', P.LOG_LIVRAISONS),
          list('vehicules', P.LOG_VEHICULES),
          list('chauffeurs', P.LOG_CHAUFFEURS),
        ],
      },
    ],
  },

  { path: '**', redirectTo: 'dashboard' },
];
