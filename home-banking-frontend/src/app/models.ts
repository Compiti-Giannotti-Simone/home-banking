export interface User {
  id: number;
  name: string;
  surname: string;
  username: string | null;
  email: string | null;
  profile_picture_url?: string | null;
  is_admin?: boolean;
  created_at?: string;
}

export interface Account {
  id: number;
  user_id?: number;
  currency: string;
  created_at?: string;
  balance?: number;
}

export interface Transaction {
  id: number;
  account_id: number;
  type: 'deposit' | 'withdrawal';
  amount: number;
  description: string;
  created_at?: string;
}

export interface LoginResponse {
  user_id: number;
  name: string;
  surname: string;
  username: string | null;
  email: string | null;
  profile_picture_url?: string | null;
  accounts: Account[];
  is_admin: boolean;
}
