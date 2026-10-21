import { Component, OnInit } from '@angular/core';
import { catchError, finalize, forkJoin, of } from 'rxjs';
import { Alert, AlertType, DeliveryProposal } from '../../models/stock.model';
import { Site } from '../../models/catalog.model';
import { StockService } from '../../services/stock.service';
import { CatalogService } from '../../services/catalog.service';
import { AuthService } from '../../services/auth.service';

@Component({
  selector: 'app-admin-deliveries',
  templateUrl: './admin-deliveries.component.html',
  styleUrls: ['./admin-deliveries.component.scss'],
})
export class AdminDeliveriesComponent implements OnInit {
  items: Alert[] = [];
  proposals: DeliveryProposal[] = [];
  sites: Site[] = [];
  loading = false;
  error: string | null = null;
  filterType: AlertType | 'ALL' = 'ALL';
  filterSiteId: number | 'ALL' = 'ALL';
  busyIds = new Set<number>();

  deliverAlert: Alert | null = null;
  deliverQty = 10;
  delivering = false;
  toast: string | null = null;

  constructor(
    readonly auth: AuthService,
    private readonly stockService: StockService,
    private readonly catalog: CatalogService
  ) {}

  get pending(): Alert[] {
    return this.items.filter((a) => {
      if (a.status === 'RESOLVED') return false;
      if (this.filterType !== 'ALL' && a.type !== this.filterType) return false;
      if (this.filterSiteId !== 'ALL' && a.siteId !== this.filterSiteId) {
        return false;
      }
      return true;
    });
  }

  ngOnInit(): void {
    this.load();
  }

  load(): void {
    this.loading = true;
    this.error = null;
    forkJoin({
      alerts: this.stockService.alerts().pipe(
        catchError((err) => {
          this.error =
            err?.error?.error?.message || err?.message || 'Erreur de chargement';
          return of([] as Alert[]);
        })
      ),
      proposals: this.stockService.proposals().pipe(
        catchError(() => of([] as DeliveryProposal[]))
      ),
      sites: this.catalog.listSites().pipe(catchError(() => of([] as Site[]))),
    })
      .pipe(finalize(() => (this.loading = false)))
      .subscribe(({ alerts, proposals, sites }) => {
        this.sites = [...sites].sort((a, b) => a.name.localeCompare(b.name, 'fr'));
        this.proposals = proposals;
        this.items = alerts.sort((a, b) =>
          a.createdAt < b.createdAt ? 1 : a.createdAt > b.createdAt ? -1 : 0
        );
      });
  }

  proposalStatus(proposal: DeliveryProposal): string {
    const manager = proposal.managerConfirmedBy ? 'manager confirmé' : 'manager en attente';
    const responsable = proposal.responsableConfirmedBy ? 'responsable confirmé' : 'responsable en attente';
    return `${manager}, ${responsable}`;
  }

  proposalFor(alert: Alert): DeliveryProposal | undefined {
    return this.proposals.find(
      (p) => p.status === 'PENDING' && (p.alertId === alert.id || p.stockId === alert.stockId)
    );
  }

  onSiteFilterChange(value: string | number): void {
    this.filterSiteId = value === 'ALL' || value === '' ? 'ALL' : Number(value);
  }

  openDeliver(alert: Alert): void {
    this.deliverAlert = alert;
    const existing = this.proposalFor(alert);
    this.deliverQty = existing?.quantity || Math.max(1, alert.threshold || 10);
  }

  closeDeliver(): void {
    this.deliverAlert = null;
    this.delivering = false;
  }

  confirmDeliver(): void {
    if (!this.deliverAlert || this.deliverQty < 1 || this.delivering) return;
    const alert = this.deliverAlert;
    this.delivering = true;
    this.busyIds.add(alert.id);
    this.stockService
      .proposeDelivery(alert.id, this.deliverQty)
      .pipe(
        catchError((err) => {
          this.error =
            err?.error?.error?.message || err?.message || 'Proposition impossible';
          return of(null);
        }),
        finalize(() => {
          this.busyIds.delete(alert.id);
          this.delivering = false;
        })
      )
      .subscribe((res) => {
        if (!res) return;
        this.closeDeliver();
        this.toast = `Proposition de ${res.proposal.quantity} unité(s) envoyée au responsable`;
        setTimeout(() => (this.toast = null), 3000);
        this.load();
      });
  }

  labelType(type: AlertType): string {
    return type === 'OUT_OF_STOCK' ? 'Rupture' : 'Stock bas';
  }

  formatDate(value?: string): string {
    if (!value) return '—';
    try {
      return new Date(value).toLocaleString('fr-FR');
    } catch {
      return value;
    }
  }
}
