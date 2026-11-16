import { Component } from '@angular/core';
import { Logo } from './logo';

/** Panneau illustré des pages de connexion et d'adhésion : champs de mil au lever du soleil. */
@Component({
  selector: 'app-auth-art',
  imports: [Logo],
  template: `
    <aside class="auth-art" aria-hidden="true">
      <svg viewBox="0 0 600 900" preserveAspectRatio="xMidYMax slice">
        <defs>
          <linearGradient id="ciel" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0" stop-color="#0e2a1f" />
            <stop offset="0.55" stop-color="#17543a" />
            <stop offset="1" stop-color="#2a7a4f" />
          </linearGradient>
          <radialGradient id="soleil" cx="0.5" cy="0.5" r="0.5">
            <stop offset="0" stop-color="#f6c35a" />
            <stop offset="0.45" stop-color="#e2a72e" stop-opacity="0.85" />
            <stop offset="1" stop-color="#e2a72e" stop-opacity="0" />
          </radialGradient>
        </defs>
        <rect width="600" height="900" fill="url(#ciel)" />
        <circle cx="400" cy="520" r="230" fill="url(#soleil)" />
        <circle cx="400" cy="520" r="62" fill="#f6c35a" />
        <path d="M0 560 C140 520 260 600 600 540 L600 900 L0 900Z" fill="#1d6a47" />
        <path d="M0 640 C160 600 300 690 600 620 L600 900 L0 900Z" fill="#17543a" />
        <path d="M0 730 C180 690 330 780 600 710 L600 900 L0 900Z" fill="#11402c" />
        <path d="M0 820 C200 780 360 860 600 800 L600 900 L0 900Z" fill="#0b2e20" />
        <g stroke="#e2a72e" stroke-width="2" stroke-linecap="round" fill="none" opacity="0.55">
          <path d="M60 880 V790 M90 885 V800 M120 880 V780 M150 888 V805 M180 882 V790" />
          <path d="M440 885 V805 M470 880 V790 M500 888 V800 M530 882 V780 M560 886 V805" />
        </g>
        <g fill="#e2a72e" opacity="0.55">
          <ellipse cx="60" cy="786" rx="4" ry="9" /><ellipse cx="120" cy="776" rx="4" ry="9" /><ellipse cx="180" cy="786" rx="4" ry="9" />
          <ellipse cx="470" cy="786" rx="4" ry="9" /><ellipse cx="530" cy="776" rx="4" ry="9" />
        </g>
      </svg>
      <div class="auth-art-text">
        <app-logo [size]="44" />
        <p class="tagline">Chaque récolte, du champ à la livraison.</p>
        <p class="tagline-sub">Production, stock, ventes et logistique de la coopérative, au même endroit.</p>
      </div>
    </aside>
  `,
})
export class AuthArt {}
