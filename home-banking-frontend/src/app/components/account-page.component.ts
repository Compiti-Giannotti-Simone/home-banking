import { CommonModule } from '@angular/common';
import { Component, OnInit, signal, computed } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, ParamMap, RouterLink } from '@angular/router';
import { AlertComponent } from './alert/alert.component';
import { ButtonComponent } from './button/button.component';
import { CardComponent } from './card/card.component';
import { Account } from '../interfaces/account';
import { Transaction } from '../interfaces/transaction';
import { AccountService } from '../services/account.service';

@Component({
  selector: 'app-account-page',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterLink, AlertComponent, ButtonComponent, CardComponent],
  templateUrl: './account-page.component.html'
})
export class AccountPageComponent implements OnInit {
  account = signal<Account | null>(null);
  transactions = signal<Transaction[]>([]);
  selectedTransaction = signal<Transaction | null>(null);
  isLoading = signal(true);
  errorMessage = signal('');

  isTransactionModalOpen = signal(false);
  transactionType = signal<'deposit' | 'withdrawal'>('deposit');
  transactionAmount = signal<number | null>(null);
  transactionDescription = signal('');
  isSubmitting = signal(false);

  isConversionModalOpen = signal(false);
  targetCurrency = signal('EUR');

  // Mock exchange rates relative to USD
  private exchangeRates: Record<string, number> = {
    USD: 1,
    EUR: 0.92,
    GBP: 0.79,
    JPY: 155.4,
    CHF: 0.91,
    BTC: 0.000015,
    ETH: 0.00028
  };

  availableCurrencies = Object.keys(this.exchangeRates);

  convertedAmount = computed(() => {
    const acc = this.account();
    if (!acc || acc.balance == null || !this.exchangeRates[acc.currency] || !this.exchangeRates[this.targetCurrency()]) {
      return null;
    }
    // Convert base to USD, then USD to target
    const amountInUSD = acc.balance / this.exchangeRates[acc.currency];
    return amountInUSD * this.exchangeRates[this.targetCurrency()];
  });

  constructor(private route: ActivatedRoute, private accountService: AccountService) {}

  ngOnInit() {
    this.route.paramMap.subscribe((params: ParamMap) => {
      const accountId = Number(params.get('id'));
      if (!Number.isFinite(accountId)) {
        this.errorMessage.set('Invalid account id.');
        this.isLoading.set(false);
        return;
      }

      this.loadAccount(accountId);
    });
  }

  loadAccount(accountId: number) {
    this.isLoading.set(true);
    this.accountService.getAccount(accountId).subscribe({
      next: (response: { account: Account }) => {
        this.account.set(response.account);
        this.loadTransactions(accountId);
      },
      error: (error: any) => {
        this.errorMessage.set(error?.error?.error ?? 'Unable to load account.');
        this.isLoading.set(false);
      }
    });
  }

  loadTransactions(accountId: number) {
    this.accountService.getTransactions(accountId).subscribe({
      next: (response: Transaction[]) => {
        this.transactions.set(response);
        this.isLoading.set(false);
      },
      error: (error: any) => {
        this.errorMessage.set(error?.error?.error ?? 'Unable to load transactions.');
        this.isLoading.set(false);
      }
    });
  }

  openTransaction(transaction: Transaction) {
    this.selectedTransaction.set(transaction);
  }

  closeTransaction() {
    this.selectedTransaction.set(null);
  }

  openTransactionModal(type: 'deposit' | 'withdrawal') {
    this.transactionType.set(type);
    this.transactionAmount.set(null);
    this.transactionDescription.set('');
    this.isTransactionModalOpen.set(true);
  }

  closeTransactionModal() {
    this.isTransactionModalOpen.set(false);
  }

  openConversionModal() {
    this.isConversionModalOpen.set(true);
  }

  closeConversionModal() {
    this.isConversionModalOpen.set(false);
  }

  submitTransaction() {
    const account = this.account();
    const amount = this.transactionAmount();
    const description = this.transactionDescription() || (this.transactionType() === 'deposit' ? 'Deposit' : 'Withdrawal');
    
    this.errorMessage.set('');

    if (!account || !amount || amount <= 0) return;

    this.isSubmitting.set(true);
    const payload = { amount, description };
    const request = this.transactionType() === 'deposit' 
      ? this.accountService.deposit(account.id, payload)
      : this.accountService.withdraw(account.id, payload);

    request.subscribe({
      next: () => {
        this.isSubmitting.set(false);
        this.closeTransactionModal();
        this.loadAccount(account.id);
      },
      error: (error: any) => {
        this.errorMessage.set(error?.error?.error ?? 'Transaction failed.');
        this.isSubmitting.set(false);
        this.closeTransactionModal();
      }
    });
  }
}
