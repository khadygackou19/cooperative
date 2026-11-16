import { Component, input } from '@angular/core';

/** Logo feuille — vert plein #2d5c46. */
@Component({
  selector: 'app-logo',
  template: `
    <span class="logo">
      <svg [attr.width]="size()" [attr.height]="size()" viewBox="0 0 48 48" aria-hidden="true">
        <rect width="48" height="48" rx="12" fill="#2d5c46" />
        <path
          d="M34 12c-10 0-18 5-18 14 0 3 1 5.500 2.500 7.500C20.500 31 23 28 27 26c-3.500 2.500-6 5.500-7.500 9 1.500 1 3.500 1.500 5.500 1.500 8 0 13-7 13-16 0-3-.5-5-1-8.500z"
          fill="#ffffff"
        />
      </svg>
      @if (withText()) {
        <span class="logo-text">Teranga <b>Agri Coop</b></span>
      }
    </span>
  `,
  styles: [
    `
      .logo { display: inline-flex; align-items: center; gap: 10px; }
      .logo-text { font-size: 1.05rem; color: inherit; line-height: 1.1; }
      .logo-text b { display: block; font-weight: 700; }
    `,
  ],
})
export class Logo {
  size = input(36);
  withText = input(true);
}
