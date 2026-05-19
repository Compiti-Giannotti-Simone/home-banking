import { CommonModule } from '@angular/common';
import { Component, OnInit } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';
import { AlertComponent } from './alert/alert.component';
import { ButtonComponent } from './button/button.component';
import { CardComponent } from './card/card.component';
import { AuthService } from '../services/auth.service';

@Component({
  selector: 'app-auth-page',
  standalone: true,
  imports: [CommonModule, FormsModule, AlertComponent, ButtonComponent, CardComponent],
  templateUrl: './auth-page.component.html'
})
export class AuthPageComponent implements OnInit {
  mode: 'login' | 'register' = 'login';
  errorMessage = '';
  isLoading = false;

  loginData = {
    identifier: '',
    password: ''
  };

  registerData = {
    name: '',
    surname: '',
    username: '',
    email: '',
    password: ''
  };

  constructor(
    private authService: AuthService,
    private router: Router,
    private route: ActivatedRoute
  ) {}

  ngOnInit() {
    this.route.data.subscribe((data: { mode?: 'login' | 'register' }) => {
      this.mode = data.mode ?? 'login';
    });
  }

  switchMode(mode: 'login' | 'register') {
    this.mode = mode;
    this.errorMessage = '';
  }

  submitLogin() {
    this.errorMessage = '';
    this.isLoading = true;

    const payload = {
      password: this.loginData.password,
      identifier: this.loginData.identifier.trim()
    };

    this.authService.login(payload).subscribe({
      next: () => {
        this.isLoading = false;
        this.router.navigate(['/user']);
      },
      error: (error: any) => {
        this.isLoading = false;
        this.errorMessage = error?.error?.error ?? 'Login failed. Please try again.';
      }
    });
  }

  submitRegister() {
    this.errorMessage = '';
    this.isLoading = true;

    const payload = {
      name: this.registerData.name,
      surname: this.registerData.surname,
      username: this.registerData.username || undefined,
      email: this.registerData.email || undefined,
      password: this.registerData.password
    };

    this.authService.register(payload).subscribe({
      next: () => {
        this.isLoading = false;
        this.router.navigate(['/user']);
      },
      error: (error: any) => {
        this.isLoading = false;
        this.errorMessage = error?.error?.error ?? 'Registration failed. Please try again.';
      }
    });
  }
}
