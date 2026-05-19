import { CommonModule } from '@angular/common';
import { Component, OnInit } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { AlertComponent } from './alert/alert.component';
import { ButtonComponent } from './button/button.component';
import { CardComponent } from './card/card.component';
import { Account, Transaction, User } from '../models';
import { AdminService } from '../services/admin.service';

@Component({
  selector: 'app-admin-page',
  standalone: true,
  imports: [CommonModule, FormsModule, AlertComponent, ButtonComponent, CardComponent],
  templateUrl: './admin-page.component.html'
})
export class AdminPageComponent implements OnInit {
  users: User[] = [];
  accounts: Account[] = [];
  transactionForm: Partial<Transaction> & { transactionId?: number; account_id?: number } = {};
  errorMessage = '';
  successMessage = '';
  isLoading = true;

  constructor(private adminService: AdminService) {}

  ngOnInit() {
    this.refresh();
  }

  refresh() {
    this.errorMessage = '';
    this.successMessage = '';
    this.isLoading = true;

    this.adminService.getUsers().subscribe({
      next: (response: { users: User[] }) => {
        this.users = response.users;
      },
      error: (error: any) => {
        this.errorMessage = error?.error?.error ?? 'Unable to load admin data.';
      }
    });

    this.adminService.getAccounts().subscribe({
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

  updateUser(user: User) {
    this.adminService
      .updateUser(user.id, {
        name: user.name,
        surname: user.surname,
        username: user.username ?? undefined,
        email: user.email ?? undefined,
        is_admin: user.is_admin ?? false
      })
      .subscribe({
        next: () => {
          this.successMessage = 'User updated.';
        },
        error: (error: any) => {
          this.errorMessage = error?.error?.error ?? 'Failed to update user.';
        }
      });
  }

  deleteUser(user: User) {
    this.adminService.deleteUser(user.id).subscribe({
      next: () => {
        this.users = this.users.filter((item) => item.id !== user.id);
        this.successMessage = 'User deleted.';
      },
      error: (error: any) => {
        this.errorMessage = error?.error?.error ?? 'Failed to delete user.';
      }
    });
  }

  updateAccount(account: Account) {
    this.adminService
      .updateAccount(account.id, {
        user_id: account.user_id,
        currency: account.currency
      })
      .subscribe({
        next: () => {
          this.successMessage = 'Account updated.';
        },
        error: (error: any) => {
          this.errorMessage = error?.error?.error ?? 'Failed to update account.';
        }
      });
  }

  deleteAccount(account: Account) {
    this.adminService.deleteAccount(account.id).subscribe({
      next: () => {
        this.accounts = this.accounts.filter((item) => item.id !== account.id);
        this.successMessage = 'Account deleted.';
      },
      error: (error: any) => {
        this.errorMessage = error?.error?.error ?? 'Failed to delete account.';
      }
    });
  }

  updateTransaction() {
    if (!this.transactionForm.transactionId) {
      this.errorMessage = 'Transaction ID is required.';
      return;
    }

    this.adminService
      .updateTransaction(this.transactionForm.transactionId, {
        amount: this.transactionForm.amount,
        description: this.transactionForm.description,
        type: this.transactionForm.type as Transaction['type'],
        account_id: this.transactionForm.account_id
      })
      .subscribe({
        next: () => {
          this.successMessage = 'Transaction updated.';
        },
        error: (error: any) => {
          this.errorMessage = error?.error?.error ?? 'Failed to update transaction.';
        }
      });
  }

  deleteTransaction() {
    if (!this.transactionForm.transactionId) {
      this.errorMessage = 'Transaction ID is required.';
      return;
    }

    this.adminService.deleteTransaction(this.transactionForm.transactionId).subscribe({
      next: () => {
        this.successMessage = 'Transaction deleted.';
      },
      error: (error: any) => {
        this.errorMessage = error?.error?.error ?? 'Failed to delete transaction.';
      }
    });
  }
}
