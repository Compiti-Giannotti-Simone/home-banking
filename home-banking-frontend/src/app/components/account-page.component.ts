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

  currencySearch = signal('');
  isCurrencyDropdownOpen = signal(false);

  availableCurrencies = signal<string[]>([
    'AUD', 'CAD', 'CHF', 'CNY', 'EUR', 'GBP', 'INR', 'JPY', 'NZD', 'USD',
    'BNB', 'BTC', 'ETH', 'SOL', 'USDC', 'USDT'
  ]);
  
  private cryptoSet = new Set<string>([
    'BNB', 'BTC', 'ETH', 'SOL', 'USDC', 'USDT'
  ]);

  convertedAmount = signal<number | null>(null);

  filteredCurrencies = computed(() => {
    const search = this.currencySearch().toLowerCase();
    const all = this.availableCurrencies();
    return search ? all.filter(c => c.toLowerCase().includes(search)) : all;
  });

  constructor(
    private route: ActivatedRoute,
    private accountService: AccountService
  ) {}

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

  toggleCurrencyDropdown() {
    this.isCurrencyDropdownOpen.update(open => !open);
    if (this.isCurrencyDropdownOpen()) {
      this.currencySearch.set('');
    }
  }

  selectCurrency(curr: string) {
    this.targetCurrency.set(curr);
    this.isCurrencyDropdownOpen.set(false);
    this.fetchConversion();
  }

  openConversionModal() {
    this.isConversionModalOpen.set(true);
    this.fetchConversion();
  }

  closeConversionModal() {
    this.isConversionModalOpen.set(false);
  }

  onTargetCurrencyChange(currency: string) {
    this.targetCurrency.set(currency);
    this.fetchConversion();
  }

  fetchConversion() {
    const acc = this.account();
    if (!acc) return;
    
    this.convertedAmount.set(null);
    const target = this.targetCurrency();
    const isCrypto = this.cryptoSet.has(target);
    
    const request = isCrypto 
      ? this.accountService.convertCrypto(acc.id, target) 
      : this.accountService.convertFiat(acc.id, target);

    request.subscribe({
      next: (res) => {
        const amount = res?.converted_balance ?? res?.converted_amount ?? null;
        this.convertedAmount.set(amount);
      },
      error: () => this.convertedAmount.set(null)
    });
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
