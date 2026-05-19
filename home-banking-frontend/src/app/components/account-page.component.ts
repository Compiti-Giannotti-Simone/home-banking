import { CommonModule } from '@angular/common';
import { Component, OnInit } from '@angular/core';
import { ActivatedRoute, ParamMap, RouterLink } from '@angular/router';
import { AlertComponent } from './alert/alert.component';
import { ButtonComponent } from './button/button.component';
import { CardComponent } from './card/card.component';
import { Account, Transaction } from '../models';
import { AccountService } from '../services/account.service';

@Component({
  selector: 'app-account-page',
  standalone: true,
  imports: [CommonModule, RouterLink, AlertComponent, ButtonComponent, CardComponent],
  templateUrl: './account-page.component.html'
})
export class AccountPageComponent implements OnInit {
  account: Account | null = null;
  transactions: Transaction[] = [];
  selectedTransaction: Transaction | null = null;
  isLoading = true;
  errorMessage = '';

  constructor(private route: ActivatedRoute, private accountService: AccountService) {}

  ngOnInit() {
    this.route.paramMap.subscribe((params: ParamMap) => {
      const accountId = Number(params.get('id'));
      if (!Number.isFinite(accountId)) {
        this.errorMessage = 'Invalid account id.';
        this.isLoading = false;
        return;
      }

      this.loadAccount(accountId);
    });
  }

  loadAccount(accountId: number) {
    this.isLoading = true;
    this.accountService.getAccount(accountId).subscribe({
      next: (response: { account: Account }) => {
        this.account = response.account;
        this.loadTransactions(accountId);
      },
      error: (error: any) => {
        this.errorMessage = error?.error?.error ?? 'Unable to load account.';
        this.isLoading = false;
      }
    });
  }

  loadTransactions(accountId: number) {
    this.accountService.getTransactions(accountId).subscribe({
      next: (response: Transaction[]) => {
        this.transactions = response;
        this.isLoading = false;
      },
      error: (error: any) => {
        this.errorMessage = error?.error?.error ?? 'Unable to load transactions.';
        this.isLoading = false;
      }
    });
  }

  openTransaction(transaction: Transaction) {
    this.selectedTransaction = transaction;
  }

  closeTransaction() {
    this.selectedTransaction = null;
  }
}
