import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, map } from 'rxjs';

import { environment } from '../../environments/environment';
import { ApiResponse, User, UserRole } from '../models/user.model';

export interface UserWritePayload {
  firstName?: string;
  lastName?: string;
  email?: string;
  password?: string;
  role?: UserRole;
  siteId?: number | null;
  active?: boolean;
}

@Injectable({ providedIn: 'root' })
export class UserService {
  private readonly baseUrl = `${environment.apiUrl}/users`;

  constructor(private readonly http: HttpClient) {}

  listAll(): Observable<User[]> {
    return this.http
      .get<ApiResponse<User[]>>(this.baseUrl)
      .pipe(map((res) => this.extract(res)));
  }

  listByRole(role: UserRole): Observable<User[]> {
    return this.http
      .get<ApiResponse<User[]>>(this.baseUrl, { params: { role } })
      .pipe(map((res) => this.extract(res)));
  }

  create(payload: UserWritePayload): Observable<User> {
    return this.http
      .post<ApiResponse<User>>(this.baseUrl, payload)
      .pipe(map((res) => this.extract(res)));
  }

  update(id: number, payload: UserWritePayload): Observable<User> {
    return this.http
      .put<ApiResponse<User>>(`${this.baseUrl}/${id}`, payload)
      .pipe(map((res) => this.extract(res)));
  }

  remove(id: number): Observable<void> {
    return this.http.delete<void>(`${this.baseUrl}/${id}`);
  }

  private extract<T>(res: ApiResponse<T>): T {
    if (!res.success || !res.data) {
      throw new Error(res.error?.message || 'Erreur API utilisateurs');
    }
    return res.data;
  }
}
