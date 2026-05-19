import { Routes } from '@angular/router';
import { AccountPageComponent } from './components/account-page.component';
import { AdminPageComponent } from './components/admin-page.component';
import { AuthPageComponent } from './components/auth-page.component';
import { UserPageComponent } from './components/user-page.component';

export const routes: Routes = [
	{ path: '', pathMatch: 'full', redirectTo: 'login' },
	{ path: 'login', component: AuthPageComponent, data: { mode: 'login' } },
	{ path: 'register', component: AuthPageComponent, data: { mode: 'register' } },
	{ path: 'user', component: UserPageComponent },
	{ path: 'accounts/:id', component: AccountPageComponent },
	{ path: 'admin', component: AdminPageComponent },
	{ path: '**', redirectTo: 'login' }
];
