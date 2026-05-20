export interface User {
  id: number;
  name: string;
  surname: string;
  username: string;
  email: string;
  is_admin?: boolean;
  created_at?: string;
}