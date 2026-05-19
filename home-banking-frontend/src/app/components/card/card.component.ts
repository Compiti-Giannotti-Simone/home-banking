import { CommonModule } from '@angular/common';
import { Component, Input } from '@angular/core';

@Component({
  selector: 'app-card',
  standalone: true,
  imports: [CommonModule],
  template: `
    <div [ngClass]="classes">
      <ng-content></ng-content>
    </div>
  `
})
export class CardComponent {
  @Input() padding = 'p-6';
  @Input() className = '';

  get classes() {
    const base = 'rounded-2xl border border-slate-800 bg-slate-900/70';
    return [base, this.padding, this.className];
  }
}
