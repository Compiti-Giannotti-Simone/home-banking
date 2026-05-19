import { Injectable, computed, signal } from '@angular/core';
import { catchError, map, of, switchMap, tap } from 'rxjs';
import { ApiService } from './api.service';
import { Account, LoginResponse, User } from '../models';

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
    return this.api.post<LoginResponse>('/auth/login', payload).pipe(
      tap((response: LoginResponse) => {
        this.userSignal.set({
          id: response.user_id,
          name: response.name,
          surname: response.surname,
          username: response.username,
          email: response.email,
          profile_picture_url: response.profile_picture_url ?? null,
          is_admin: response.is_admin
        });
      })
    );
  }

  register(payload: { name: string; surname: string; username?: string; email?: string; password: string }) {
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
