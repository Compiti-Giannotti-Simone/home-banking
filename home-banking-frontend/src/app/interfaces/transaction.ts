export interface Transaction {
  id: number;
  account_id: number;
  type: 'deposit' | 'withdrawal';
  amount: number;
  description: string;
  created_at?: string;
}