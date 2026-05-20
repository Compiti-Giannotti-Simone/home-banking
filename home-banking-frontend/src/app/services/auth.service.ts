import { Injectable, computed, signal } from '@angular/core';
import { catchError, map, of, switchMap, tap } from 'rxjs';
import { ApiService } from './api.service';
import { Account } from '../interfaces/account';
import { User } from '../interfaces/user';
import { registerData } from '../interfaces/registerData';

interface CurrentUserResponse {
  user: User;
  accounts: Account[];
}

@Injectable({ providedIn: 'root' })
export class AuthService {
  private readonly userSignal = signal<User | null>(null);
  readonly user = this.userSignal.asReadonly();
  readonly isAdmin = computed(() => Boolean(this.userSignal()?.is_admin));

  constructor(private api: ApiService) {}

  loadSession() {
    return this.api.get<CurrentUserResponse>('/users/me').pipe(
      tap((response: CurrentUserResponse) => {
        this.userSignal.set({
          ...response.user,
          is_admin: Boolean(response.user.is_admin)
        });
      }),
      map(() => this.userSignal()),
      catchError(() => {
        this.userSignal.set(null);
        return of(null);
      })
    );
  }

  login(payload: { identifier: string; password: string }) {
    return this.api.post<User>('/auth/login', payload).pipe(
      tap((response: User) => {
        this.userSignal.set({
          id: response.id,
          name: response.name,
          surname: response.surname,
          username: response.username,
          email: response.email,
          is_admin: response.is_admin
        });
      })
    );
  }

  register(payload: registerData) {
    return this.api.post<{ message: string; user_id: number }>('/auth/register', payload).pipe(
      switchMap(() => this.loadSession())
    );
  }

  logout() {
    return this.api.post<{ message: string }>('/auth/logout', {}).pipe(
      tap(() => this.userSignal.set(null)),
      catchError(() => {
        this.userSignal.set(null);
        return of({ message: 'Logged out' });
      })
    );
  }
}
