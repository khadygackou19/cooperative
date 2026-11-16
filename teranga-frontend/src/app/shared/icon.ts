import { Component, computed, inject, input } from '@angular/core';
import { DomSanitizer } from '@angular/platform-browser';
import { ICONS } from './icons';

/** Icône vectorielle : <app-icon name="truck" />. La taille suit la taille du texte par défaut. */
@Component({
  selector: 'app-icon',
  template: `<svg
    class="icon"
    [style.width]="size()"
    [style.height]="size()"
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    stroke-width="1.9"
    stroke-linecap="round"
    stroke-linejoin="round"
    aria-hidden="true"
    [innerHTML]="paths()"
  ></svg>`,
  styles: [':host { display: inline-flex; flex: none; } .icon { display: block; }'],
})
export class Icon {
  private sanitizer = inject(DomSanitizer);

  name = input.required<string>();
  size = input('1.15em');

  // Contenu statique, fourni par icons.ts (jamais saisi par un utilisateur).
  paths = computed(() => this.sanitizer.bypassSecurityTrustHtml(ICONS[this.name()] ?? ''));
}
