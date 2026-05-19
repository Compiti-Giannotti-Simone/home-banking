import { CommonModule } from '@angular/common';
import { Component, Input } from '@angular/core';
import { RouterLink } from '@angular/router';

@Component({
  selector: 'app-button',
  standalone: true,
  imports: [CommonModule, RouterLink],
  template: `
    <button
      [attr.type]="type"
      [disabled]="disabled"
      [routerLink]="routerLink"
      [ngClass]="classes"
    >
      <ng-content></ng-content>
    </button>
  `
})
export class ButtonComponent {
  @Input() variant: 'primary' | 'success' | 'danger' | 'outline' | 'ghost' = 'primary';
  @Input() size: 'xs' | 'sm' | 'md' = 'md';
  @Input() shape: 'rounded' | 'pill' = 'rounded';
  @Input() type: 'button' | 'submit' | 'reset' = 'button';
  @Input() disabled = false;
  @Input() routerLink?: string | any[];
  @Input() className = '';

  get classes() {
    const base =
      'inline-flex items-center justify-center font-semibold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400 disabled:cursor-not-allowed disabled:opacity-60';
    const sizeMap = {
      xs: 'px-3 py-1 text-xs',
      sm: 'px-3 py-1.5 text-sm',
      md: 'px-4 py-2 text-sm'
    };
    const shapeMap = {
      rounded: 'rounded-lg',
      pill: 'rounded-full'
    };
    const variantMap = {
      primary: 'bg-indigo-500 text-white hover:bg-indigo-400',
      success: 'bg-emerald-500 text-slate-900 hover:bg-emerald-400',
      danger: 'bg-rose-500/80 text-white hover:bg-rose-400',
      outline: 'border border-slate-700 text-slate-200 hover:bg-slate-800',
      ghost: 'text-slate-400 hover:text-white'
    };

    return [
      base,
      sizeMap[this.size],
      shapeMap[this.shape],
      variantMap[this.variant],
      this.className
    ];
  }
}
