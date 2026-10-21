import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import {
  RouterModule,
  Routes,
} from '@angular/router';
import { FormsModule, ReactiveFormsModule } from '@angular/forms';
import { GuestGuard, AuthGuard, RoleGuard } from './guards/auth.guard';
import { LoginComponent } from './pages/login/login.component';
import { DashboardComponent } from './pages/dashboard/dashboard.component';
import { AlertsComponent } from './pages/alerts/alerts.component';
import { MovementsComponent } from './pages/movements/movements.component';
import { ResponsableComponent } from './pages/responsable/responsable.component';
import { AdminDeliveriesComponent } from './pages/admin-deliveries/admin-deliveries.component';
import { AdminSitesComponent } from './pages/admin-sites/admin-sites.component';
import { AdminDashboardComponent } from './pages/admin-dashboard/admin-dashboard.component';
import { AdminAnalysisComponent } from './pages/admin-analysis/admin-analysis.component';

const routes: Routes = [
  { path: '', pathMatch: 'full', redirectTo: 'login' },
  {
    path: 'login',
    component: LoginComponent,
    canActivate: [GuestGuard],
  },
  {
    path: 'responsable',
    component: ResponsableComponent,
    canActivate: [AuthGuard, RoleGuard],
    data: { roles: ['STOCK_MANAGER'] },
  },
  {
    path: 'admin/tableau',
    component: AdminDashboardComponent,
    canActivate: [AuthGuard, RoleGuard],
    data: { roles: ['ADMIN', 'MANAGER'] },
  },
  {
    path: 'admin/analyses',
    component: AdminAnalysisComponent,
    canActivate: [AuthGuard, RoleGuard],
    data: { roles: ['ADMIN'] },
  },
  {
    path: 'admin',
    component: AdminDeliveriesComponent,
    canActivate: [AuthGuard, RoleGuard],
    data: { roles: ['ADMIN'] },
  },
  {
    path: 'admin/historique',
    component: MovementsComponent,
    canActivate: [AuthGuard, RoleGuard],
    data: { roles: ['ADMIN', 'MANAGER'] },
  },
  {
    path: 'admin/sites',
    component: AdminSitesComponent,
    canActivate: [AuthGuard, RoleGuard],
    data: { roles: ['ADMIN'] },
  },
  // Anciennes routes : redirigées via guards selon le rôle
  {
    path: 'dashboard',
    component: DashboardComponent,
    canActivate: [AuthGuard, RoleGuard],
    data: { roles: ['ADMIN'] },
  },
  {
    path: 'alerts',
    component: AlertsComponent,
    canActivate: [AuthGuard, RoleGuard],
    data: { roles: ['ADMIN'] },
  },
  {
    path: 'movements',
    redirectTo: 'admin/historique',
  },
  { path: '**', redirectTo: 'login' },
];

@NgModule({
  imports: [CommonModule, FormsModule, ReactiveFormsModule, RouterModule.forRoot(routes, { useHash: true })],
  exports: [RouterModule, CommonModule, FormsModule, ReactiveFormsModule],
})
export class AppRoutingModule {}
