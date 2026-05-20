import { CommonModule } from '@angular/common';
import { Component, OnInit, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { AlertComponent } from './alert/alert.component';
import { ButtonComponent } from './button/button.component';
import { CardComponent } from './card/card.component';
import { Account } from '../interfaces/account';
import { AccountService } from '../services/account.service';
import { AuthService } from '../services/auth.service';

@Component({
  selector: 'app-user-page',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterLink, AlertComponent, ButtonComponent, CardComponent],
  templateUrl: './user-page.component.html'
})
export class UserPageComponent implements OnInit {
  accounts = signal<Account[]>([]);
  isLoading = signal(true);
  errorMessage = signal('');

  selectedAccountId = signal<number | null>(null);
  isSubmitting = signal(false);

  isCreateAccountModalOpen = signal(false);
  newAccountCurrency = signal('USD');
  isCreatingAccount = signal(false);

  constructor(private authService: AuthService, private accountService: AccountService) {}

  get user() {
    return this.authService.user();
  }

  ngOnInit() {
    this.authService.loadSession().subscribe();
    this.fetchAccounts();
  }

  fetchAccounts() {
    this.isLoading.set(true);
    this.errorMessage.set('');
    this.accountService.getAccounts().subscribe({
      next: (response: { accounts: Account[] }) => {
        this.accounts.set(response.accounts);
        this.isLoading.set(false);
      },
      error: (error: any) => {
        this.errorMessage.set(error?.error?.error ?? 'Unable to load accounts.');
        this.isLoading.set(false);
      }
    });
  }

  openCreateAccountModal() {
    this.newAccountCurrency.set('USD');
    this.isCreateAccountModalOpen.set(true);
  }

  closeCreateAccountModal() {
    this.isCreateAccountModalOpen.set(false);
  }

  submitCreateAccount() {
    if (!this.newAccountCurrency()) return;

    this.isCreatingAccount.set(true);
    this.accountService.createAccount(this.newAccountCurrency()).subscribe({
      next: () => {
        this.isCreatingAccount.set(false);
        this.closeCreateAccountModal();
        this.fetchAccounts();
      },
      error: (error: any) => {
        this.errorMessage.set(error?.error?.error ?? 'Failed to create account.');
        this.isCreatingAccount.set(false);
        this.closeCreateAccountModal();
      }
    });
  }
}
