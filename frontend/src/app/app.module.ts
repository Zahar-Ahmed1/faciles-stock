import { NgModule } from '@angular/core';
import { BrowserModule } from '@angular/platform-browser';
import {
  HTTP_INTERCEPTORS,
  provideHttpClient,
  withInterceptorsFromDi,
} from '@angular/common/http';

import { AppRoutingModule } from './app-routing.module';
import { AppComponent } from './app.component';

import { LoginComponent } from './pages/login/login.component';
import { DashboardComponent } from './pages/dashboard/dashboard.component';
import { AlertsComponent } from './pages/alerts/alerts.component';
import { MovementsComponent } from './pages/movements/movements.component';
import { ResponsableComponent } from './pages/responsable/responsable.component';
import { AdminDeliveriesComponent } from './pages/admin-deliveries/admin-deliveries.component';
import { AdminSitesComponent } from './pages/admin-sites/admin-sites.component';
import { AdminDashboardComponent } from './pages/admin-dashboard/admin-dashboard.component';
import { AdminAnalysisComponent } from './pages/admin-analysis/admin-analysis.component';
import { StockCardComponent } from './components/stock-card/stock-card.component';
import { NavbarComponent } from './components/navbar/navbar.component';

import { JwtInterceptor } from './interceptors/jwt.interceptor';

@NgModule({
  declarations: [
    AppComponent,
    LoginComponent,
    DashboardComponent,
    AlertsComponent,
    MovementsComponent,
    ResponsableComponent,
    AdminDeliveriesComponent,
    AdminSitesComponent,
    AdminDashboardComponent,
    AdminAnalysisComponent,
    StockCardComponent,
    NavbarComponent,
  ],
  imports: [BrowserModule, AppRoutingModule],
  providers: [
    provideHttpClient(withInterceptorsFromDi()),
    {
      provide: HTTP_INTERCEPTORS,
      useClass: JwtInterceptor,
      multi: true,
    },
  ],
  bootstrap: [AppComponent],
})
export class AppModule {}
