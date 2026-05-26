import { Injectable } from '@angular/core';
import { map } from 'rxjs';
import { Account } from '../interfaces/account';
import { Transaction } from '../interfaces/transaction';
import { ApiService } from './api.service';

@Injectable({ providedIn: 'root' })
export class AccountService {
  constructor(private api: ApiService) {}

  getAccounts() {
    return this.api.get<{ accounts: Account[] }>('/accounts');
  }

  createAccount(currency: string) {
    return this.api.post<{ account: Account }>('/accounts', { currency });
  }

  getAccount(accountId: number) {
    return this.api.get<{ account: Account }>(`/accounts/${accountId}`);
  }

  getTransactions(accountId: number) {
    return this.api.get<Array<Transaction | unknown[]>>(`/accounts/${accountId}/transactions`).pipe(
      map((transactions: Array<Transaction | unknown[]>) =>
        transactions.map((item: Transaction | unknown[]) => this.normalizeTransaction(item))
      )
    );
  }

  getTransaction(accountId: number, transactionId: number) {
    return this.api.get<Transaction>(`/accounts/${accountId}/transactions/${transactionId}`);
  }

  deposit(accountId: number, payload: { amount: number; description: string }) {
    return this.api.post(`/accounts/${accountId}/deposit`, payload);
  }

  withdraw(accountId: number, payload: { amount: number; description: string }) {
    return this.api.post(`/accounts/${accountId}/withdrawal`, payload);
  }

  convertFiat(accountId: number, currency: string) {
    return this.api.get<any>(`/accounts/${accountId}/convert/fiat?to=${currency}`);
  }

  convertCrypto(accountId: number, crypto: string) {
    return this.api.get<any>(`/accounts/${accountId}/convert/crypto?to=${crypto}`);
  }

  private normalizeTransaction(item: Transaction | unknown[]): Transaction {
    if (Array.isArray(item)) {
      const [id, account_id, type, amount, description, created_at] = item as [
        number,
        number,
        'deposit' | 'withdrawal',
        number,
        string,
        string
      ];
      return {
        id,
        account_id,
        type,
        amount: Number(amount),
        description,
        created_at
      };
    }

    return {
      ...item,
      amount: Number((item as Transaction).amount)
    } as Transaction;
  }
}
