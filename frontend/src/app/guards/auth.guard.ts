import { Injectable } from '@angular/core';
import {
  ActivatedRouteSnapshot,
  CanActivate,
  Router,
  UrlTree,
} from '@angular/router';
import { AuthService } from '../services/auth.service';
import { UserRole } from '../models/user.model';

@Injectable({ providedIn: 'root' })
export class AuthGuard implements CanActivate {
  constructor(
    private readonly auth: AuthService,
    private readonly router: Router
  ) {}

  canActivate(): true | UrlTree {
    if (this.auth.isAuthenticated) return true;
    return this.router.createUrlTree(['/login']);
  }
}

@Injectable({ providedIn: 'root' })
export class RoleGuard implements CanActivate {
  constructor(
    private readonly auth: AuthService,
    private readonly router: Router
  ) {}

  canActivate(route: ActivatedRouteSnapshot): true | UrlTree {
    const roles = (route.data?.['roles'] ?? []) as UserRole[];
    if (!roles || roles.length === 0) return true;
    if (!this.auth.isAuthenticated) {
      return this.router.createUrlTree(['/login']);
    }
    if (this.auth.hasRole(...roles)) return true;
    return this.router.createUrlTree([this.auth.homePath()]);
  }
}

@Injectable({ providedIn: 'root' })
export class GuestGuard implements CanActivate {
  constructor(
    private readonly auth: AuthService,
    private readonly router: Router
  ) {}

  canActivate(): true | UrlTree {
    if (!this.auth.isAuthenticated) return true;
    return this.router.createUrlTree([this.auth.homePath()]);
  }
}
