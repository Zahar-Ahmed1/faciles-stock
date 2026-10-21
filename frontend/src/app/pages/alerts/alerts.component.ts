import { Component, OnInit } from '@angular/core';
import { catchError, finalize, of, tap } from 'rxjs';
import { Alert, AlertType } from '../../models/stock.model';
import { StockService } from '../../services/stock.service';
import { AuthService } from '../../services/auth.service';

@Component({
  selector: 'app-alerts',
  templateUrl: './alerts.component.html',
  styleUrls: ['./alerts.component.scss'],
})
export class AlertsComponent implements OnInit {
  items: Alert[] = [];
  loading = false;
  error: string | null = null;

  siteId: number | null = null;
  filterType: AlertType | 'ALL' = 'ALL';
  filterStatus: 'UNREAD' | 'ALL' = 'UNREAD';

  busyIds = new Set<number>();

  constructor(
    readonly auth: AuthService,
    private readonly stockService: StockService
  ) {
    const stored = localStorage.getItem('fs_selected_site');
    if (stored) this.siteId = Number(stored) || null;
  }

  get filtered(): Alert[] {
    return this.items.filter((a) => {
      if (this.filterType !== 'ALL' && a.type !== this.filterType) return false;
      if (this.filterStatus === 'UNREAD' && a.status !== 'UNREAD') return false;
      return true;
    });
  }

  get countUnread(): number {
    return this.items.filter((a) => a.status === 'UNREAD').length;
  }

  ngOnInit(): void {
    this.load();
  }

  load(): void {
    this.loading = true;
    this.error = null;
    const onlyUnread = this.filterStatus === 'UNREAD';
    const request = onlyUnread
      ? this.stockService.alerts({ onlyUnread: true })
      : this.siteId
      ? this.stockService.alerts({ siteId: this.siteId })
      : this.stockService.alerts();
    request
      .pipe(
        catchError((err) => {
          this.error =
            err?.error?.error?.message || err?.message || 'Erreur de chargement';
          return of([] as Alert[]);
        }),
        finalize(() => (this.loading = false))
      )
      .subscribe((list) => {
        this.items = list.sort((a, b) =>
          a.createdAt < b.createdAt ? 1 : a.createdAt > b.createdAt ? -1 : 0
        );
      });
  }

  markRead(alert: Alert): void {
    if (this.busyIds.has(alert.id)) return;
    if (alert.status !== 'UNREAD') return;
    this.busyIds.add(alert.id);
    this.stockService
      .markAlertRead(alert.id)
      .pipe(
        catchError(() => of(null)),
        finalize(() => this.busyIds.delete(alert.id)),
        tap(() => (alert.status = 'READ'))
      )
      .subscribe();
  }

  resolve(alert: Alert): void {
    if (this.busyIds.has(alert.id)) return;
    if (alert.status === 'RESOLVED') return;
    this.busyIds.add(alert.id);
    this.stockService
      .resolveAlert(alert.id)
      .pipe(
        catchError(() => of(null)),
        finalize(() => this.busyIds.delete(alert.id)),
        tap(() => (alert.status = 'RESOLVED'))
      )
      .subscribe();
  }

  labelType(t: AlertType): string {
    return t === 'LOW_STOCK' ? 'Stock faible' : 'Rupture';
  }

  onFilterTypeChange(v: string): void {
    this.filterType = v === 'LOW_STOCK' || v === 'OUT_OF_STOCK' ? v : 'ALL';
    this.load();
  }

  colorClassForType(t: AlertType): string {
    return t === 'LOW_STOCK' ? 'type-low' : 'type-out';
  }

  labelStatus(s: string): string {
    switch (s) {
      case 'UNREAD':
        return 'Non lu';
      case 'READ':
        return 'Lu';
      case 'RESOLVED':
        return 'Résolu';
      default:
        return s;
    }
  }

  classStatus(s: string): string {
    switch (s) {
      case 'UNREAD':
        return 'status-unread';
      case 'READ':
        return 'status-read';
      case 'RESOLVED':
        return 'status-resolved';
      default:
        return '';
    }
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
      });
    } catch {
      return iso;
    }
  }

  trackById(_index: number, x: { id: number }): number {
    return x.id;
  }
}
