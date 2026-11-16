import { Component, input } from '@angular/core';

/** Logo : feuille blanche sur pastille verte, avec un grain doré. */
@Component({
  selector: 'app-logo',
  template: `
    <span class="logo">
      <svg [attr.width]="size()" [attr.height]="size()" viewBox="0 0 48 48" aria-hidden="true">
        <rect width="48" height="48" rx="13" fill="#17543a" />
        <path
          d="M34 12c-10 0-18 5-18 14 0 3 1 5.500 2.500 7.500C20.500 31 23 28 27 26c-3.500 2.500-6 5.500-7.500 9 1.500 1 3.500 1.500 5.500 1.500 8 0 13-7 13-16 0-3-.5-5-1-8.500z"
          fill="#ffffff"
        />
        <circle cx="35.500" cy="12.500" r="4.200" fill="#e2a72e" />
      </svg>
      @if (withText()) {
        <span class="logo-text">Teranga <b>Agri Coop</b></span>
      }
    </span>
  `,
  styles: [
    `
      .logo { display: inline-flex; align-items: center; gap: 11px; }
      .logo-text { font-family: var(--font-display); font-size: 1.05rem; color: inherit; line-height: 1.1; font-weight: 500; }
      .logo-text b { display: block; font-weight: 700; font-size: 1.12rem; letter-spacing: -0.01em; }
    `,
  ],
})
export class Logo {
  size = input(36);
  withText = input(true);
}
