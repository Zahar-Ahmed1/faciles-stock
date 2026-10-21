import { Component, OnDestroy } from '@angular/core';
import { Router, NavigationEnd } from '@angular/router';
import { AuthService } from '../../services/auth.service';
import { filter, Subject, takeUntil } from 'rxjs';

@Component({
  selector: 'app-navbar',
  templateUrl: './navbar.component.html',
  styleUrls: ['./navbar.component.scss'],
})
export class NavbarComponent implements OnDestroy {
  private readonly destroy$ = new Subject<void>();
  currentRoute = '/admin';

  constructor(
    readonly auth: AuthService,
    private readonly router: Router
  ) {
    this.router.events
      .pipe(
        takeUntil(this.destroy$),
        filter((e): e is NavigationEnd => e instanceof NavigationEnd)
      )
      .subscribe((e) => {
        this.currentRoute = e.urlAfterRedirects.split('?')[0];
      });
    const initial = this.router.url.split('?')[0];
    if (initial && initial !== '/') this.currentRoute = initial;
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
  }

  get navItems(): Array<{ path: string; label: string; icon: string }> {
    if (this.auth.hasRole('ADMIN')) {
      return [
        { path: '/admin/tableau', label: 'Tableau', icon: '📊' },
        { path: '/admin/analyses', label: 'Analyses', icon: '📈' },
        { path: '/admin', label: 'Livraisons', icon: '🚚' },
        { path: '/admin/historique', label: 'Historique', icon: '📜' },
        { path: '/admin/sites', label: 'Sites', icon: '🏢' },
      ];
    }
    if (this.auth.hasRole('MANAGER')) {
      return [
        { path: '/admin/tableau', label: 'Tableau', icon: '📊' },
        { path: '/admin/historique', label: 'Historique', icon: '📜' },
      ];
    }
    return [{ path: '/responsable', label: 'Stock', icon: '📦' }];
  }

  get userDisplayName(): string {
    const u = this.auth.currentUser;
    if (!u) return '';
    return `${u.firstName} ${u.lastName}`.trim();
  }

  get avatarInitials(): string {
    const u = this.auth.currentUser;
    if (!u) return '?';
    return (u.firstName?.[0] ?? '') + (u.lastName?.[0] ?? '');
  }

  get roleLabel(): string {
    switch (this.auth.currentUser?.role) {
      case 'ADMIN':
        return 'Admin Facilyx';
      case 'MANAGER':
        return 'Manager Facilyx';
      case 'STOCK_MANAGER':
        return 'Responsable';
      default:
        return '';
    }
  }

  isActive(path: string): boolean {
    if (path === '/admin') {
      return this.currentRoute === '/admin';
    }
    return this.currentRoute === path || this.currentRoute.startsWith(path + '/');
  }

  goTo(path: string): void {
    void this.router.navigateByUrl(path);
  }

  logout(): void {
    this.auth.logout();
    this.destroy$.next();
    void this.router.navigate(['/login']);
  }
}
