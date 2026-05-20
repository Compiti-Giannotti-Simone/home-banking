import { CommonModule } from '@angular/common';
import { Component, Input, HostBinding } from '@angular/core';

@Component({
  selector: 'app-alert',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './alert.component.html',
  host: {
    '[class]': 'classes'
  }
})
export class AlertComponent {
  @Input() type: 'error' | 'success' | 'info' = 'info';
  @Input() class = '';

  get classes() {
    const base = 'block rounded-lg border px-4 py-3 text-sm';
    const typeMap = {
      error: 'border-rose-500/40 bg-rose-500/10 text-rose-200',
      success: 'border-emerald-500/40 bg-emerald-500/10 text-emerald-200',
      info: 'border-slate-700 bg-slate-900/60 text-slate-300'
    };

    return [base, typeMap[this.type], this.class].filter(Boolean).join(' ');
  }
}
