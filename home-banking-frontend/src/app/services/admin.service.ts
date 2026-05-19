import { Injectable } from '@angular/core';
import { map } from 'rxjs';
import { ApiService } from './api.service';
import { Account, Transaction, User } from '../models';

@Injectable({ providedIn: 'root' })
export class AdminService {
  constructor(private api: ApiService) {}

  getUsers() {
    return this.api.get<{ users: User[] }>('/admin/users').pipe(
      map((response: { users: User[] }) => ({
        users: response.users.map((user) => ({
          ...user,
          is_admin: Boolean(user.is_admin)
        }))
      }))
    );
  }

  updateUser(id: number, payload: Partial<User> & { password?: string; is_admin?: number | boolean }) {
    return this.api.put(`/admin/users/${id}`, payload);
  }

  deleteUser(id: number) {
    return this.api.delete(`/admin/users/${id}`);
  }

  getAccounts() {
    return this.api.get<{ accounts: Account[] }>('/admin/accounts');
  }

  updateAccount(id: number, payload: { user_id?: number; currency?: string }) {
    return this.api.put(`/admin/accounts/${id}`, payload);
  }

  deleteAccount(id: number) {
    return this.api.delete(`/admin/accounts/${id}`);
  }

  updateTransaction(id: number, payload: Partial<Transaction> & { account_id?: number }) {
    return this.api.put(`/admin/transactions/${id}`, payload);
  }

  deleteTransaction(id: number) {
    return this.api.delete(`/admin/transactions/${id}`);
  }
}
