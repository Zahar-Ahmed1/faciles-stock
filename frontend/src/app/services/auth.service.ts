import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { BehaviorSubject, Observable, tap, map } from 'rxjs';

import { environment } from '../../environments/environment';
import {
  ApiResponse,
  LoginRequest,
  LoginResponse,
  User,
} from '../models/user.model';

const TOKEN_KEY = 'fs_token';
const USER_KEY = 'fs_user';

@Injectable({ providedIn: 'root' })
export class AuthService {
  private readonly apiUrl = `${environment.apiUrl}/auth`;

  private readonly _currentUser$ = new BehaviorSubject<User | null>(null);
  readonly currentUser$ = this._currentUser$.asObservable();

  private readonly _isAuthenticated$ = new BehaviorSubject<boolean>(false);
  readonly isAuthenticated$ = this._isAuthenticated$.asObservable();

  constructor(private readonly http: HttpClient) {
    this.restoreSession();
  }

  get currentUser(): User | null {
    return this._currentUser$.getValue();
  }

  get isAuthenticated(): boolean {
    return this._isAuthenticated$.getValue();
  }

  get token(): string | null {
    return localStorage.getItem(TOKEN_KEY);
  }

  login(credentials: LoginRequest): Observable<User> {
    return this.http
      .post<ApiResponse<LoginResponse>>(`${this.apiUrl}/login`, credentials)
      .pipe(
        map((res) => this.extractData(res)),
        tap((payload) => this.saveSession(payload.token, payload.user)),
        map((payload) => payload.user)
      );
  }

  logout(): void {
    localStorage.removeItem(TOKEN_KEY);
    localStorage.removeItem(USER_KEY);
    this._currentUser$.next(null);
    this._isAuthenticated$.next(false);
  }

  hasRole(...roles: Array<User['role']>): boolean {
    const user = this.currentUser;
    if (!user) return false;
    return roles.includes(user.role);
  }

  get isFacilyxAdmin(): boolean {
    return this.hasRole('ADMIN', 'MANAGER');
  }

  get isResponsable(): boolean {
    return this.hasRole('STOCK_MANAGER');
  }

  homePath(): string {
    if (this.isResponsable) return '/responsable';
    if (this.isFacilyxAdmin) return '/admin/tableau';
    return '/login';
  }

  private saveSession(token: string, user: User): void {
    localStorage.setItem(TOKEN_KEY, token);
    localStorage.setItem(USER_KEY, JSON.stringify(user));
    this._currentUser$.next(user);
    this._isAuthenticated$.next(true);
  }

  private restoreSession(): void {
    try {
      const token = localStorage.getItem(TOKEN_KEY);
      const rawUser = localStorage.getItem(USER_KEY);
      if (!token || !rawUser) return;
      const user = JSON.parse(rawUser) as User;
      this._currentUser$.next(user);
      this._isAuthenticated$.next(true);
    } catch {
      this.logout();
    }
  }

  private extractData<T>(res: ApiResponse<T>): T {
    if (!res.success || !res.data) {
      const err = res.error;
      throw new Error(err?.message || 'Erreur inconnue');
    }
    return res.data;
  }
}
