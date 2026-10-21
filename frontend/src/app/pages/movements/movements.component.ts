import { Component, OnInit } from '@angular/core';
import { catchError, finalize, forkJoin, map, Observable, of, switchMap } from 'rxjs';
import { downloadExcel, downloadPdf, movementRows } from './history-export';
import {
  MovementType,
  StockMovement,
} from '../../models/stock.model';
import { StockService } from '../../services/stock.service';
import { AuthService } from '../../services/auth.service';
import { CatalogService } from '../../services/catalog.service';
import { Product, Site } from '../../models/catalog.model';

@Component({
  selector: 'app-movements',
  templateUrl: './movements.component.html',
  styleUrls: ['./movements.component.scss'],
})
export class MovementsComponent implements OnInit {
  items: StockMovement[] = [];
  total = 0;
  loading = false;
  exporting = false;
  error: string | null = null;

  siteId: number | null = null;
  productId: number | null = null;
  type: MovementType | 'ALL' = 'ALL';
  from: string | null = null;
  to: string | null = null;
  page = 1;
  perPage = 25;

  sites: Site[] = [];
  products: Product[] = [];

  constructor(
    readonly auth: AuthService,
    private readonly stockService: StockService,
    private readonly catalog: CatalogService
  ) {}

  get totalPages(): number {
    return Math.max(1, Math.ceil(this.total / this.perPage));
  }

  get pageNumbers(): number[] {
    const tp = this.totalPages;
    const p = this.page;
    const arr: number[] = [];
    for (let i = Math.max(1, p - 2); i <= Math.min(tp, p + 2); i++) arr.push(i);
    return arr;
  }

  get ownSiteOnly(): boolean {
    return this.auth.hasRole('MANAGER');
  }

  ngOnInit(): void {
    if (this.ownSiteOnly) {
      this.siteId = this.auth.currentUser?.siteId ?? null;
    }
    const tasks: unknown[] = [];
    if (this.auth.hasRole('ADMIN', 'MANAGER')) {
      tasks.push(
        this.catalog.listSites().pipe(
          catchError(() => of([] as Site[]))
        ).subscribe((list) => (this.sites = list))
      );
    }
    tasks.push(
      this.catalog.listProducts().pipe(
        catchError(() => of([] as Product[]))
      ).subscribe((list) => (this.products = list))
    );
    tasks.forEach(() => {});
    this.load();
  }

  resetPagination(): void {
    this.page = 1;
    this.load();
  }

  onSiteFilterChange(v: unknown): void {
    if (this.ownSiteOnly) return;
    this.siteId = v != null && v !== '' ? Number(v) : null;
    this.resetPagination();
  }

  onProductFilterChange(v: unknown): void {
    this.productId = v != null && v !== '' ? Number(v) : null;
    this.resetPagination();
  }

  onPerPageChange(v: unknown): void {
    const n = Number(v);
    if (n > 0) this.perPage = n;
    this.resetPagination();
  }

  load(): void {
    if (this.ownSiteOnly && !this.siteId) {
      this.items = [];
      this.total = 0;
      this.error = 'Aucun site n’est affecté à ce compte.';
      return;
    }
    this.loading = true;
    this.error = null;
    this.stockService
      .movements({
        ...this.currentFilters(),
        page: this.page,
        perPage: this.perPage,
      })
      .pipe(
        catchError((err) => {
          this.error =
            err?.error?.error?.message || err?.message || 'Erreur de chargement';
          return of({ items: [] as StockMovement[], total: 0 });
        }),
        finalize(() => (this.loading = false))
      )
      .subscribe((res) => {
        this.items = res.items;
        this.total = res.total;
      });
  }

  exportFile(kind: 'pdf' | 'excel'): void {
    if (!this.auth.hasRole('ADMIN', 'MANAGER') || this.exporting || this.total === 0) return;
    this.exporting = true;
    this.error = null;
    this.collectFiltered()
      .pipe(
        catchError((err) => {
          this.error = err?.error?.error?.message || 'Export impossible.';
          return of([] as StockMovement[]);
        }),
        finalize(() => (this.exporting = false))
      )
      .subscribe((items) => {
        if (!items.length) return;
        const rows = movementRows(items, (type) => this.labelType(type as MovementType), (value) => this.formatDate(value));
        const title = 'Historique des mouvements';
        if (kind === 'excel') downloadExcel(title, rows);
        else downloadPdf(title, rows);
      });
  }

  private currentFilters(): {
    siteId?: number;
    productId?: number;
    type?: MovementType;
    from?: string;
    to?: string;
  } {
    return {
      siteId: this.ownSiteOnly
        ? (this.auth.currentUser?.siteId ?? undefined)
        : (this.siteId ?? undefined),
      productId: this.productId ?? undefined,
      type: this.type === 'ALL' ? undefined : this.type,
      from: this.from ?? undefined,
      to: this.to ? `${this.to} 23:59:59` : undefined,
    };
  }

  private collectFiltered(): Observable<StockMovement[]> {
    const filters = this.currentFilters();
    const pageSize = 200;
    return this.stockService.movements({ ...filters, page: 1, perPage: pageSize }).pipe(
      switchMap((first) => {
        const pages = Math.ceil(first.total / pageSize);
        if (pages <= 1) return of(first.items);
        const rest = [];
        for (let page = 2; page <= pages; page++) {
          rest.push(this.stockService.movements({ ...filters, page, perPage: pageSize }));
        }
        return forkJoin(rest).pipe(
          map((next) => first.items.concat(...next.map((page) => page.items)))
        );
      })
    );
  }

  prevPage(): void {
    if (this.page > 1) {
      this.page--;
      this.load();
    }
  }

  nextPage(): void {
    if (this.page < this.totalPages) {
      this.page++;
      this.load();
    }
  }

  goToPage(p: number): void {
    if (p >= 1 && p <= this.totalPages && p !== this.page) {
      this.page = p;
      this.load();
    }
  }

  labelType(t: MovementType): string {
    switch (t) {
      case 'ENTRY':
        return 'Entrée';
      case 'EXIT':
        return 'Sortie';
      case 'ADJUSTMENT':
        return 'Ajustement';
      default:
        return t;
    }
  }

  classType(t: MovementType): string {
    switch (t) {
      case 'ENTRY':
        return 't-entry';
      case 'EXIT':
        return 't-exit';
      case 'ADJUSTMENT':
        return 't-adjust';
      default:
        return '';
    }
  }

  productName(id: number | null | undefined): string {
    if (id == null) return '—';
    const p = this.products.find((x) => x.id === id);
    return p ? p.name : ('Produit #' + id);
  }

  productImage(id: number | null | undefined): string | undefined {
    if (id == null) return undefined;
    return this.products.find((x) => x.id === id)?.imageUrl;
  }

  siteName(id: number | null | undefined): string {
    if (id == null) return '—';
    const s = this.sites.find((x) => x.id === id);
    return s ? s.name : ('Site #' + id);
  }

  signedQuantity(m: StockMovement): string {
    const n = m.quantity;
    if (m.type === 'EXIT') return '-' + n;
    if (m.type === 'ENTRY') return '+' + n;
    return '±' + n;
  }

  formatDate(iso: string): string {
    try {
      const d = new Date(iso);
      return d.toLocaleString('fr-FR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
      });
    } catch {
      return iso;
    }
  }

  trackById(_index: number, x: { id: number }): number {
    return x.id;
  }
}
