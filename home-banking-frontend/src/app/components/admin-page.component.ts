import { CommonModule } from '@angular/common';
import { Component, OnInit, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { AlertComponent } from './alert/alert.component';
import { ButtonComponent } from './button/button.component';
import { CardComponent } from './card/card.component';
import { AdminService } from '../services/admin.service';
import { User } from '../interfaces/user';
import { Account } from '../interfaces/account';
import { Transaction } from '../interfaces/transaction';  

@Component({
  selector: 'app-admin-page',
  standalone: true,
  imports: [CommonModule, FormsModule, AlertComponent, ButtonComponent, CardComponent],
  templateUrl: './admin-page.component.html'
})
export class AdminPageComponent implements OnInit {
  users = signal<User[]>([]);
  accounts = signal<Account[]>([]);
  transactionForm = signal<Partial<Transaction> & { transactionId?: number; account_id?: number }>({});
  errorMessage = signal('');
  successMessage = signal('');
  isLoading = signal(true);

  constructor(private adminService: AdminService) {}

  ngOnInit() {
    this.refresh();
  }

  refresh() {
    this.errorMessage.set('');
    this.successMessage.set('');
    this.isLoading.set(true);

    this.adminService.getUsers().subscribe({
      next: (response: { users: User[] }) => {
        this.users.set(response.users);
      },
      error: (error: any) => {
        this.errorMessage.set(error?.error?.error ?? 'Unable to load admin data.');
      }
    });

    this.adminService.getAccounts().subscribe({
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
          this.successMessage.set('User updated.');
        },
        error: (error: any) => {
          this.errorMessage.set(error?.error?.error ?? 'Failed to update user.');
        }
      });
  }

  deleteUser(user: User) {
    this.adminService.deleteUser(user.id).subscribe({
      next: () => {
        this.users.update((items) => items.filter((item) => item.id !== user.id));
        this.successMessage.set('User deleted.');
      },
      error: (error: any) => {
        this.errorMessage.set(error?.error?.error ?? 'Failed to delete user.');
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
          this.successMessage.set('Account updated.');
        },
        error: (error: any) => {
          this.errorMessage.set(error?.error?.error ?? 'Failed to update account.');
        }
      });
  }

  deleteAccount(account: Account) {
    this.adminService.deleteAccount(account.id).subscribe({
      next: () => {
        this.accounts.update((items) => items.filter((item) => item.id !== account.id));
        this.successMessage.set('Account deleted.');
      },
      error: (error: any) => {
        this.errorMessage.set(error?.error?.error ?? 'Failed to delete account.');
      }
    });
  }

  updateTransaction() {
    const transactionId = this.transactionId;
    if (!transactionId) {
      this.errorMessage.set('Transaction ID is required.');
      return;
    }

    const transactionForm = this.transactionForm();
    this.adminService
      .updateTransaction(transactionId, {
        amount: transactionForm.amount,
        description: transactionForm.description,
        type: transactionForm.type as Transaction['type'],
        account_id: transactionForm.account_id
      })
      .subscribe({
        next: () => {
          this.successMessage.set('Transaction updated.');
        },
        error: (error: any) => {
          this.errorMessage.set(error?.error?.error ?? 'Failed to update transaction.');
        }
      });
  }

  deleteTransaction() {
    const transactionId = this.transactionId;
    if (!transactionId) {
      this.errorMessage.set('Transaction ID is required.');
      return;
    }

    this.adminService.deleteTransaction(transactionId).subscribe({
      next: () => {
        this.successMessage.set('Transaction deleted.');
      },
      error: (error: any) => {
        this.errorMessage.set(error?.error?.error ?? 'Failed to delete transaction.');
      }
    });
  }

  get transactionId() {
    return this.transactionForm().transactionId;
  }

  set transactionId(value: number | null | undefined) {
    this.transactionForm.update((form) => ({
      ...form,
      transactionId: value ?? undefined
    }));
  }

  get transactionAccountId() {
    return this.transactionForm().account_id;
  }

  set transactionAccountId(value: number | null | undefined) {
    this.transactionForm.update((form) => ({
      ...form,
      account_id: value ?? undefined
    }));
  }

  get transactionAmount() {
    return this.transactionForm().amount;
  }

  set transactionAmount(value: number | null | undefined) {
    this.transactionForm.update((form) => ({
      ...form,
      amount: value ?? undefined
    }));
  }

  get transactionType() {
    return this.transactionForm().type ?? '';
  }

  set transactionType(value: string) {
    this.transactionForm.update((form) => ({
      ...form,
      type: value as Transaction['type']
    }));
  }

  get transactionDescription() {
    return this.transactionForm().description ?? '';
  }

  set transactionDescription(value: string) {
    this.transactionForm.update((form) => ({
      ...form,
      description: value
    }));
  }
}
