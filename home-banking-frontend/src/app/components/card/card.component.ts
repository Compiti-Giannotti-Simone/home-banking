import { CommonModule } from '@angular/common';
import { Component, Input, HostBinding } from '@angular/core';

@Component({
  selector: 'app-card',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './card.component.html',
  host: {
    '[class]': 'classes'
  }
})
export class CardComponent {
  @Input() padding = 'p-6';
  @Input() class = '';

  get classes() {
    const base = 'block rounded-2xl border border-slate-800 bg-slate-900/70';
    return [base, this.padding, this.class].filter(Boolean).join(' ');
  }
}
