import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, map } from 'rxjs';

import { environment } from '../../environments/environment';
import { ApiResponse } from '../models/user.model';
import { Client, Site, SitePayload, Category, Product } from '../models/catalog.model';

@Injectable({ providedIn: 'root' })
export class CatalogService {
  private readonly baseUrl = environment.apiUrl;

  constructor(private readonly http: HttpClient) {}

  listClients(): Observable<Client[]> {
    return this.http
      .get<ApiResponse<Client[]>>(`${this.baseUrl}/clients`)
      .pipe(map((r) => this.extract(r)));
  }

  createClient(payload: { name: string }): Observable<Client> {
    return this.http
      .post<ApiResponse<Client>>(`${this.baseUrl}/clients`, payload)
      .pipe(map((r) => this.extract(r)));
  }

  getClient(id: number): Observable<Client> {
    return this.http
      .get<ApiResponse<Client>>(`${this.baseUrl}/clients/${id}`)
      .pipe(map((r) => this.extract(r)));
  }

  listSites(): Observable<Site[]> {
    return this.http
      .get<ApiResponse<Site[]>>(`${this.baseUrl}/sites`)
      .pipe(map((r) => this.extract(r)));
  }

  getSite(id: number): Observable<Site> {
    return this.http
      .get<ApiResponse<Site>>(`${this.baseUrl}/sites/${id}`)
      .pipe(map((r) => this.extract(r)));
  }

  listSitesByClient(clientId: number): Observable<Site[]> {
    return this.http
      .get<ApiResponse<Site[]>>(`${this.baseUrl}/clients/${clientId}/sites`)
      .pipe(map((r) => this.extract(r)));
  }

  createSite(payload: SitePayload): Observable<Site> {
    return this.http
      .post<ApiResponse<Site>>(`${this.baseUrl}/sites`, payload)
      .pipe(map((r) => this.extract(r)));
  }

  updateSite(id: number, payload: SitePayload): Observable<Site> {
    return this.http
      .put<ApiResponse<Site>>(`${this.baseUrl}/sites/${id}`, payload)
      .pipe(map((r) => this.extract(r)));
  }

  deleteSite(id: number): Observable<void> {
    return this.http.delete(`${this.baseUrl}/sites/${id}`).pipe(map(() => undefined));
  }

  listCategories(): Observable<Category[]> {
    return this.http
      .get<ApiResponse<Category[]>>(`${this.baseUrl}/categories`)
      .pipe(map((r) => this.extract(r)));
  }

  listProducts(): Observable<Product[]> {
    return this.http
      .get<ApiResponse<Product[]>>(`${this.baseUrl}/products`)
      .pipe(map((r) => this.extract(r)));
  }

  createProduct(
    payload: {
      name: string;
      quantity?: number;
      minimumQuantity?: number;
      maximumQuantity?: number;
      unit?: string;
      imageUrl?: string;
    },
    image?: File | null
  ): Observable<{ product: Product; stock: import('../models/stock.model').ClientStock }> {
    const body = image ? this.withImage(payload, image) : payload;
    return this.http
      .post<
        ApiResponse<{
          product: Product;
          stock: import('../models/stock.model').ClientStock;
        }>
      >(`${this.baseUrl}/products`, body)
      .pipe(map((r) => this.extract(r)));
  }

  updateProduct(
    id: number,
    payload: {
      name?: string;
      quantity?: number;
      minimumQuantity?: number;
      maximumQuantity?: number;
      unit?: string;
      imageUrl?: string;
    },
    image?: File | null
  ): Observable<{ product: Product; stock: import('../models/stock.model').ClientStock }> {
    const body = image ? this.withImage(payload, image) : payload;
    const request = image
      ? this.http.post<
          ApiResponse<{
            product: Product;
            stock: import('../models/stock.model').ClientStock;
          }>
        >(`${this.baseUrl}/products/${id}`, body)
      : this.http.put<
          ApiResponse<{
            product: Product;
            stock: import('../models/stock.model').ClientStock;
          }>
        >(`${this.baseUrl}/products/${id}`, body);
    return request.pipe(map((r) => this.extract(r)));
  }

  private withImage(
    payload: Record<string, string | number | undefined>,
    image: File
  ): FormData {
    const body = new FormData();
    Object.entries(payload).forEach(([key, value]) => {
      if (value !== undefined && value !== null) {
        body.append(key, String(value));
      }
    });
    body.append('image', image);
    return body;
  }

  deleteProduct(id: number): Observable<void> {
    return this.http.delete(`${this.baseUrl}/products/${id}`).pipe(map(() => undefined));
  }

  private extract<T>(res: ApiResponse<T>): T {
    if (!res.success || !res.data) {
      const err = res.error;
      throw new Error(err?.message || 'Erreur API catalog');
    }
    return res.data;
  }
}
