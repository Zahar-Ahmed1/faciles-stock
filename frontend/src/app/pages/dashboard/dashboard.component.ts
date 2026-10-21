import { Component, OnInit, OnDestroy } from '@angular/core';
import { ActivatedRoute } from '@angular/router';
import {
  catchError,
  finalize,
  forkJoin,
  of,
  Subject,
  switchMap,
  takeUntil,
  tap,
} from 'rxjs';
import { AuthService } from '../../services/auth.service';
import { StockService } from '../../services/stock.service';
import { CatalogService } from '../../services/catalog.service';
import { Alert, ClientStock, StockOperationResponse } from '../../models/stock.model';
import { Site } from '../../models/catalog.model';

interface DashboardStats {
  totalProducts: number;
  normalCount: number;
  lowStockCount: number;
  outOfStockCount: number;
  totalUnits: number;
  unreadAlerts: number;
}

@Component({
  selector: 'app-dashboard',
  templateUrl: './dashboard.component.html',
  styleUrls: ['./dashboard.component.scss'],
})
export class DashboardComponent implements OnInit, OnDestroy {
  stocks: ClientStock[] = [];
  loading = false;
  error: string | null = null;

  busyIds = new Map<number, 'increment' | 'decrement'>();

  toast: { text: string; type: 'success' | 'error' } | null = null;
  private readonly toastTimer$ = new Subject<void>();
  private readonly destroy$ = new Subject<void>();

  sites: Site[] = [];
  siteId: number = 1;
  siteLoading = false;

  alerts: Alert[] = [];
  alertsLoading = false;
  showAlertsPreview = true;

  stats: DashboardStats = {
    totalProducts: 0,
    normalCount: 0,
    lowStockCount: 0,
    outOfStockCount: 0,
    totalUnits: 0,
    unreadAlerts: 0,
  };

  constructor(
    readonly auth: AuthService,
    private readonly route: ActivatedRoute,
    private readonly stockService: StockService,
    private readonly catalog: CatalogService
  ) {}

  get year(): number {
    return new Date().getFullYear();
  }

  get selectedSiteName(): string {
    const s = this.sites.find((x) => x.id === this.siteId);
    return s ? s.name : ('Site #' + this.siteId);
  }

  get canChangeSite(): boolean {
    return this.auth.hasRole('ADMIN', 'MANAGER');
  }

  get recentAlerts(): Alert[] {
    return this.alerts
      .filter((a) => a.status !== 'RESOLVED')
      .slice(0, 5);
  }

  ngOnInit(): void {
    const stored = localStorage.getItem('fs_selected_site');
    const siteParam = this.route.snapshot.queryParamMap.get('siteId');
    if (siteParam) {
      this.siteId = Number(siteParam) || this.siteId;
      localStorage.setItem('fs_selected_site', String(this.siteId));
    } else if (stored) {
      this.siteId = Number(stored) || this.siteId;
    }

    if (this.canChangeSite) {
      this.siteLoading = true;
      this.catalog
        .listSites()
        .pipe(
          takeUntil(this.destroy$),
          catchError(() => of([] as Site[])),
          finalize(() => (this.siteLoading = false))
        )
        .subscribe((list) => {
          this.sites = list;
          if (this.sites.length > 0 && !this.sites.find((s) => s.id === this.siteId)) {
            this.siteId = this.sites[0].id;
            localStorage.setItem('fs_selected_site', String(this.siteId));
          }
          this.refreshAll();
        });
    } else {
      this.refreshAll();
    }
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    this.toastTimer$.complete();
  }

  onSiteChange(val: number | string): void {
    this.siteId = Number(val);
    localStorage.setItem('fs_selected_site', String(this.siteId));
    this.refreshAll();
  }

  refreshAll(): void {
    this.loadStocks();
    this.loadAlerts();
  }

  loadStocks(): void {
    this.loading = true;
    this.error = null;
    this.stockService
      .listBySite(this.siteId)
      .pipe(
        takeUntil(this.destroy$),
        catchError((err) => {
          this.error =
            err?.error?.error?.message ||
            err?.message ||
            'Impossible de charger le stock';
          return of([]);
        }),
        finalize(() => (this.loading = false))
      )
      .subscribe((list) => {
        this.stocks = list;
        this.computeStats();
      });
  }

  loadAlerts(): void {
    this.alertsLoading = true;
    this.stockService
      .alerts({ siteId: this.siteId })
      .pipe(
        takeUntil(this.destroy$),
        catchError(() => of([] as Alert[])),
        finalize(() => (this.alertsLoading = false))
      )
      .subscribe((list) => {
        this.alerts = list.sort((a, b) =>
          a.createdAt < b.createdAt ? 1 : a.createdAt > b.createdAt ? -1 : 0
        );
        this.computeStats();
      });
  }

  private computeStats(): void {
    const res: DashboardStats = {
      totalProducts: this.stocks.length,
      normalCount: 0,
      lowStockCount: 0,
      outOfStockCount: 0,
      totalUnits: 0,
      unreadAlerts: this.alerts.filter((a) => a.status === 'UNREAD').length,
    };
    this.stocks.forEach((s) => {
      const q = s.quantity ?? 0;
      const min = s.minimumQuantity ?? 0;
      res.totalUnits += q;
      const status = s.status
        ? s.status
        : q === 0
        ? 'OUT_OF_STOCK'
        : q <= min
        ? 'LOW_STOCK'
        : 'NORMAL';
      if (status === 'NORMAL') res.normalCount++;
      else if (status === 'LOW_STOCK') res.lowStockCount++;
      else res.outOfStockCount++;
    });
    this.stats = res;
  }

  decrementStock(id: number): void {
    if (this.busyIds.has(id)) return;
    const idx = this.stocks.findIndex((s) => s.id === id);
    if (idx === -1) return;
    if ((this.stocks[idx].quantity ?? 0) <= 0) {
      this.showToast('Stock déjà à 0', 'error');
      return;
    }
    this.runOp(id, 'decrement', (s) =>
      this.stockService.decrement(s.id).pipe(
        tap((r) => {
          this.applyOperationResult(idx, r);
          if (r.alertCreated) this.loadAlerts();
        })
      )
    );
  }

  incrementStock(id: number): void {
    if (this.busyIds.has(id)) return;
    const idx = this.stocks.findIndex((s) => s.id === id);
    if (idx === -1) return;
    this.runOp(id, 'increment', (s) =>
      this.stockService.increment(s.id).pipe(
        tap((r) => {
          this.applyOperationResult(idx, r);
        })
      )
    );
  }

  private runOp(
    id: number,
    op: 'increment' | 'decrement',
    handlerFactory: (s: ClientStock) => unknown
  ): void {
    const stock = this.stocks.find((s) => s.id === id);
    if (!stock) return;
    this.busyIds.set(id, op);

    of(true)
      .pipe(
        takeUntil(this.destroy$),
        switchMap(
          () =>
            handlerFactory(stock) as ReturnType<typeof this.stockService.increment>
        ),
        catchError((err) => {
          const msg =
            err?.error?.error?.message || err?.message || 'Erreur inconnue';
          this.showToast(msg, 'error');
          return of(null);
        }),
        finalize(() => this.busyIds.delete(id))
      )
      .subscribe((r) => {
        if (r) {
          this.showToast(
            op === 'increment' ? '+1 unité ajoutée' : '−1 unité retirée',
            'success'
          );
          this.computeStats();
        }
      });
  }

  private applyOperationResult(
    idx: number,
    r: StockOperationResponse
  ): void {
    const current = this.stocks[idx];
    if (!current) return;
    this.stocks[idx] = {
      ...current,
      quantity: r.quantity,
      status: r.status,
      minimumQuantity: r.minimumQuantity,
      productName: r.productName || current.productName,
    };
  }

  markAlertRead(alert: Alert): void {
    if (alert.status !== 'UNREAD') return;
    this.stockService
      .markAlertRead(alert.id)
      .pipe(catchError(() => of(null)))
      .subscribe(() => {
        alert.status = 'READ';
        this.computeStats();
      });
  }

  private showToast(text: string, type: 'success' | 'error'): void {
    this.toast = { text, type };
    this.toastTimer$.next();
    setTimeout(() => {
      this.toast = null;
    }, 2200);
  }

  labelAlertType(t: Alert['type']): string {
    return t === 'LOW_STOCK' ? 'Stock faible' : 'Rupture';
  }

  classAlertType(t: Alert['type']): string {
    return t === 'LOW_STOCK' ? 'type-low' : 'type-out';
  }

  formatDate(iso: string): string {
    try {
      const d = new Date(iso);
      return d.toLocaleString('fr-FR', {
        day: '2-digit',
        month: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
      });
    } catch {
      return iso;
    }
  }

  trackByStockId(_index: number, s: ClientStock): number {
    return s.id;
  }

  trackByAlertId(_index: number, a: Alert): number {
    return a.id;
  }
}
