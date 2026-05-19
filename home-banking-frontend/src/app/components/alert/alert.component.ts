import { CommonModule } from '@angular/common';
import { Component, Input } from '@angular/core';

@Component({
  selector: 'app-alert',
  standalone: true,
  imports: [CommonModule],
  template: `
    <div [ngClass]="classes">
      <ng-content></ng-content>
    </div>
  `
})
export class AlertComponent {
  @Input() type: 'error' | 'success' | 'info' = 'info';
  @Input() className = '';

  get classes() {
    const base = 'rounded-lg border px-4 py-3 text-sm';
    const typeMap = {
      error: 'border-rose-500/40 bg-rose-500/10 text-rose-200',
      success: 'border-emerald-500/40 bg-emerald-500/10 text-emerald-200',
      info: 'border-slate-700 bg-slate-900/60 text-slate-300'
    };

    return [base, typeMap[this.type], this.className];
  }
}
