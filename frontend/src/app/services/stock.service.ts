import { Injectable } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable, map } from 'rxjs';

import { environment } from '../../environments/environment';
import { ApiResponse } from '../models/user.model';
import {
  Alert,
  ClientStock,
  ConfirmProposalResponse,
  DeliveryProposal,
  MovementType,
  ProposalStatus,
  ProposeDeliveryResponse,
  StockMovement,
  StockOperationResponse,
} from '../models/stock.model';

@Injectable({ providedIn: 'root' })
export class StockService {
  private readonly stockUrl = `${environment.apiUrl}/stocks`;
  private readonly movementUrl = `${environment.apiUrl}/stock-movements`;
  private readonly alertUrl = `${environment.apiUrl}/alerts`;

  constructor(private readonly http: HttpClient) {}

  listAll(): Observable<ClientStock[]> {
    return this.http
      .get<ApiResponse<ClientStock[]>>(this.stockUrl)
      .pipe(map((r) => this.extract(r)));
  }

  listBySite(siteId: number): Observable<ClientStock[]> {
    return this.http
      .get<ApiResponse<ClientStock[]>>(`${this.stockUrl}/site/${siteId}`)
      .pipe(map((r) => this.extract(r)));
  }

  getOne(id: number): Observable<ClientStock> {
    return this.http
      .get<ApiResponse<ClientStock>>(`${this.stockUrl}/${id}`)
      .pipe(map((r) => this.extract(r)));
  }

  decrement(id: number, quantity = 1): Observable<StockOperationResponse> {
    const suffix = quantity === 1 ? '/decrement' : `/decrement/${quantity}`;
    return this.http
      .post<ApiResponse<StockOperationResponse>>(
        `${this.stockUrl}/${id}${suffix}`,
        {}
      )
      .pipe(map((r) => this.extract(r)));
  }

  increment(id: number, quantity = 1): Observable<StockOperationResponse> {
    const suffix = quantity === 1 ? '/increment' : `/increment/${quantity}`;
    return this.http
      .post<ApiResponse<StockOperationResponse>>(
        `${this.stockUrl}/${id}${suffix}`,
        {}
      )
      .pipe(map((r) => this.extract(r)));
  }

  movements(
    filters: {
      siteId?: number;
      productId?: number;
      type?: MovementType;
      from?: string;
      to?: string;
      page?: number;
      perPage?: number;
    } = {}
  ): Observable<{ items: StockMovement[]; total: number }> {
    let params = new HttpParams();
    if (filters.siteId) {
      params = params.set('siteId', filters.siteId);
      params = params.set('site_id', filters.siteId);
    }
    if (filters.productId) {
      params = params.set('productId', filters.productId);
      params = params.set('product_id', filters.productId);
    }
    if (filters.type) params = params.set('type', filters.type);
    if (filters.from) params = params.set('from', filters.from);
    if (filters.to) params = params.set('to', filters.to);
    if (filters.page) params = params.set('page', filters.page);
    if (filters.perPage) {
      params = params.set('perPage', filters.perPage);
      params = params.set('per_page', filters.perPage);
    }

    return this.http
      .get<ApiResponse<{ items: StockMovement[]; total: number }>>(
        this.movementUrl,
        { params }
      )
      .pipe(map((r) => this.extract(r)));
  }

  movementsBySite(siteId: number): Observable<StockMovement[]> {
    return this.http
      .get<ApiResponse<StockMovement[]>>(
        `${this.movementUrl}/site/${siteId}`
      )
      .pipe(map((r) => this.extract(r)));
  }

  alerts(filters: { siteId?: number; onlyUnread?: boolean } = {}): Observable<
    Alert[]
  > {
    let url = this.alertUrl;
    if (filters.onlyUnread) url += '/unread';
    else if (filters.siteId) url += `/site/${filters.siteId}`;

    return this.http
      .get<ApiResponse<Alert[]>>(url)
      .pipe(map((r) => this.extract(r)));
  }

  markAlertRead(id: number): Observable<void> {
    return this.http
      .put<ApiResponse<void>>(`${this.alertUrl}/${id}/read`, {})
      .pipe(map((r) => this.extract(r)));
  }

  resolveAlert(id: number): Observable<void> {
    return this.http
      .put<ApiResponse<void>>(`${this.alertUrl}/${id}/resolve`, {})
      .pipe(map((r) => this.extract(r)));
  }

  proposeDelivery(id: number, quantity: number): Observable<ProposeDeliveryResponse> {
    return this.http
      .post<ApiResponse<ProposeDeliveryResponse>>(
        `${this.alertUrl}/${id}/deliver`,
        { quantity }
      )
      .pipe(map((r) => this.extract(r)));
  }

  proposals(status?: ProposalStatus): Observable<DeliveryProposal[]> {
    let params = new HttpParams();
    if (status) params = params.set('status', status);
    return this.http
      .get<ApiResponse<DeliveryProposal[]>>(
        `${environment.apiUrl}/delivery-proposals`,
        { params }
      )
      .pipe(map((r) => this.extract(r)));
  }

  confirmProposal(id: number): Observable<ConfirmProposalResponse> {
    return this.http
      .post<ApiResponse<ConfirmProposalResponse>>(
        `${environment.apiUrl}/delivery-proposals/${id}/confirm`,
        {}
      )
      .pipe(map((r) => this.extract(r)));
  }

  rejectProposal(id: number): Observable<ConfirmProposalResponse> {
    return this.http
      .post<ApiResponse<ConfirmProposalResponse>>(
        `${environment.apiUrl}/delivery-proposals/${id}/reject`,
        {}
      )
      .pipe(map((r) => this.extract(r)));
  }

  private extract<T>(res: ApiResponse<T>): T {
    if (!res.success || !res.data) {
      const err = res.error;
      throw new Error(err?.message || 'Erreur API');
    }
    return res.data;
  }
}
