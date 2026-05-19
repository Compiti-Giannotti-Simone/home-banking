import { CommonModule } from '@angular/common';
import { Component, OnInit } from '@angular/core';
import { RouterLink } from '@angular/router';
import { AlertComponent } from './alert/alert.component';
import { ButtonComponent } from './button/button.component';
import { CardComponent } from './card/card.component';
import { Account } from '../models';
import { AccountService } from '../services/account.service';
import { AuthService } from '../services/auth.service';

@Component({
  selector: 'app-user-page',
  standalone: true,
  imports: [CommonModule, RouterLink, AlertComponent, ButtonComponent, CardComponent],
  templateUrl: './user-page.component.html'
})
export class UserPageComponent implements OnInit {
  accounts: Account[] = [];
  isLoading = true;
  errorMessage = '';

  constructor(private authService: AuthService, private accountService: AccountService) {}

  get user() {
    return this.authService.user();
  }

  ngOnInit() {
    this.authService.loadSession().subscribe();
    this.fetchAccounts();
  }

  fetchAccounts() {
    this.isLoading = true;
    this.accountService.getAccounts().subscribe({
      next: (response: { accounts: Account[] }) => {
        this.accounts = response.accounts;
        this.isLoading = false;
      },
      error: (error: any) => {
        this.errorMessage = error?.error?.error ?? 'Unable to load accounts.';
        this.isLoading = false;
      }
    });
  }
}
