import { CommonModule } from '@angular/common';
import { Component, OnInit, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';
import { AlertComponent } from './alert/alert.component';
import { ButtonComponent } from './button/button.component';
import { CardComponent } from './card/card.component';
import { AuthService } from '../services/auth.service';
import { User } from '../interfaces/user';
import { registerData } from '../interfaces/registerData';

@Component({
  selector: 'app-auth-page',
  standalone: true,
  imports: [CommonModule, FormsModule, AlertComponent, ButtonComponent, CardComponent],
  templateUrl: './auth-page.component.html'
})
export class AuthPageComponent implements OnInit {
  mode = signal<'login' | 'register'>('login');
  errorMessage = signal('');
  isLoading = signal(false);

  loginData = signal({
    identifier: '',
    password: ''
  });

  registerData = signal({
    name: '',
    surname: '',
    username: '',
    email: '',
    password: ''
  });

  constructor(
    private authService: AuthService,
    private router: Router,
    private route: ActivatedRoute
  ) {}

  ngOnInit() {
    this.route.data.subscribe((data: { mode?: 'login' | 'register' }) => {
      this.mode.set(data.mode ?? 'login');
    });
  }

  switchMode(mode: 'login' | 'register') {
    this.mode.set(mode);
    this.errorMessage.set('');
  }

  submitLogin() {
    this.errorMessage.set('');
    this.isLoading.set(true);

    const payload = {
      password: this.loginData().password,
      identifier: this.loginData().identifier.trim()
    };

    this.authService.login(payload).subscribe({
      next: () => {
        this.isLoading.set(false);
        this.router.navigate(['/user']);
      },
      error: (error: any) => {
        this.isLoading.set(false);
        this.errorMessage.set(error?.error?.error ?? 'Login failed. Please try again.');
      }
    });
  }

  submitRegister() {
    this.errorMessage.set('');
    this.isLoading.set(true);

    const payload: registerData = 
    {
      name: this.registerData().name,
      surname: this.registerData().surname,
      username: this.registerData().username,
      email: this.registerData().email,
      password: this.registerData().password
    };

    this.authService.register(payload).subscribe({
      next: () => {
        this.isLoading.set(false);
        this.router.navigate(['/user']);
      },
      error: (error: any) => {
        this.isLoading.set(false);
        this.errorMessage.set(error?.error?.error ?? 'Registration failed. Please try again.');
      }
    });
  }

  get loginIdentifier() {
    return this.loginData().identifier;
  }

  set loginIdentifier(value: string) {
    this.loginData.update((data) => ({
      ...data,
      identifier: value
    }));
  }

  get loginPassword() {
    return this.loginData().password;
  }

  set loginPassword(value: string) {
    this.loginData.update((data) => ({
      ...data,
      password: value
    }));
  }

  get registerName() {
    return this.registerData().name;
  }

  set registerName(value: string) {
    this.registerData.update((data) => ({
      ...data,
      name: value
    }));
  }

  get registerSurname() {
    return this.registerData().surname;
  }

  set registerSurname(value: string) {
    this.registerData.update((data) => ({
      ...data,
      surname: value
    }));
  }

  get registerUsername() {
    return this.registerData().username;
  }

  set registerUsername(value: string) {
    this.registerData.update((data) => ({
      ...data,
      username: value
    }));
  }

  get registerEmail() {
    return this.registerData().email;
  }

  set registerEmail(value: string) {
    this.registerData.update((data) => ({
      ...data,
      email: value
    }));
  }

  get registerPassword() {
    return this.registerData().password;
  }

  set registerPassword(value: string) {
    this.registerData.update((data) => ({
      ...data,
      password: value
    }));
  }
}
