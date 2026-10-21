import { Component, OnInit } from '@angular/core';
import { catchError, forkJoin, map, Observable, of, switchMap } from 'rxjs';
import { Category, Product, Site } from '../../models/catalog.model';
import {
  Alert,
  ClientStock,
  DeliveryProposal,
  MovementType,
  ProposalStatus,
  StockMovement,
  StockStatus,
} from '../../models/stock.model';
import { CatalogService } from '../../services/catalog.service';
import { StockService } from '../../services/stock.service';

type PeriodKey = '7' | '30' | '90' | 'year' | 'all' | 'custom';
type StatusFilter = 'ALL' | StockStatus;
type FlowFilter = 'ALL' | MovementType;

interface ActivityBucket {
  key: string;
  label: string;
  entry: number;
  exit: number;
}

interface ProductRow {
  productId: number;
  name: string;
  category: string;
  exits: number;
  entries: number;
  net: number;
  stock: number;
}

interface SiteRow {
  id: number;
  name: string;
  units: number;
  low: number;
  empty: number;
  entries: number;
  exits: number;
}

@Component({
  selector: 'app-admin-analysis',
  templateUrl: './admin-analysis.component.html',
  styleUrls: ['./admin-analysis.component.scss'],
})
export class AdminAnalysisComponent implements OnInit {
  loading = false;
  error: string | null = null;

  sites: Site[] = [];
  categories: Category[] = [];
  products: Product[] = [];
  stocks: ClientStock[] = [];
  movements: StockMovement[] = [];
  alerts: Alert[] = [];
  proposals: DeliveryProposal[] = [];

  period: PeriodKey = '30';
  fromDate = '';
  toDate = '';
  clientId: number | null = null;
  siteIds: number[] = [];
  categoryId: number | null = null;
  productId: number | null = null;
  query = '';
  stockStatus: StatusFilter = 'ALL';
  flow: FlowFilter = 'ALL';
  filtersOpen = true;

  readonly periods: Array<{ id: PeriodKey; label: string }> = [
    { id: '7', label: '7 jours' },
    { id: '30', label: '30 jours' },
    { id: '90', label: '90 jours' },
    { id: 'year', label: 'Cette année' },
    { id: 'all', label: 'Tout' },
  ];

  readonly statuses: Array<{ id: StatusFilter; label: string }> = [
    { id: 'ALL', label: 'Tous les états' },
    { id: 'NORMAL', label: 'En stock' },
    { id: 'LOW_STOCK', label: 'Stock bas' },
    { id: 'OUT_OF_STOCK', label: 'Rupture' },
  ];

  readonly flows: Array<{ id: FlowFilter; label: string }> = [
    { id: 'ALL', label: 'Tous les flux' },
    { id: 'ENTRY', label: 'Entrées' },
    { id: 'EXIT', label: 'Sorties' },
    { id: 'ADJUSTMENT', label: 'Ajustements' },
  ];

  constructor(
    private readonly catalog: CatalogService,
    private readonly stockApi: StockService
  ) {}

  ngOnInit(): void {
    this.setPeriod('30');
    this.load();
  }

  get clients(): Array<{ id: number; name: string }> {
    const names = new Map<number, string>();
    for (const site of this.sites) {
      if (!names.has(site.clientId)) {
        names.set(site.clientId, site.clientName || `Client #${site.clientId}`);
      }
    }
    return [...names.entries()]
      .map(([id, name]) => ({ id, name }))
      .sort((a, b) => a.name.localeCompare(b.name, 'fr'));
  }

  get visibleSites(): Site[] {
    return this.sites
      .filter((site) => !this.clientId || site.clientId === this.clientId)
      .sort((a, b) => a.name.localeCompare(b.name, 'fr'));
  }

  get productChoices(): Product[] {
    return this.products
      .filter((product) => !this.categoryId || product.categoryId === this.categoryId)
      .sort((a, b) => a.name.localeCompare(b.name, 'fr'));
  }

  get scopedStocks(): ClientStock[] {
    return this.stocks.filter((stock) => this.stockMatches(stock, true));
  }

  get healthStocks(): ClientStock[] {
    return this.stocks.filter((stock) => this.stockMatches(stock, false));
  }

  get scopedMovements(): StockMovement[] {
    return this.movements.filter((movement) => this.movementMatches(movement));
  }

  get stockUnits(): number {
    return this.scopedStocks.reduce((sum, stock) => sum + stock.quantity, 0);
  }

  get exitQty(): number {
    return this.sumType('EXIT');
  }

  get entryQty(): number {
    return this.sumType('ENTRY');
  }

  get adjustmentQty(): number {
    return this.sumType('ADJUSTMENT');
  }

  get openAlerts(): number {
    return this.alerts.filter(
      (alert) => alert.status !== 'RESOLVED' && this.alertMatches(alert, false)
    ).length;
  }

  get periodAlerts(): number {
    return this.alerts.filter((alert) => this.alertMatches(alert, true)).length;
  }

  get confirmedQty(): number {
    return this.proposals
      .filter((proposal) => proposal.status === 'CONFIRMED' && this.proposalMatches(proposal))
      .reduce((sum, proposal) => sum + proposal.quantity, 0);
  }

  get health(): { normal: number; low: number; empty: number; total: number } {
    const counts = { normal: 0, low: 0, empty: 0, total: 0 };
    for (const stock of this.healthStocks) {
      counts.total += 1;
      if (stock.status === 'OUT_OF_STOCK') counts.empty += 1;
      else if (stock.status === 'LOW_STOCK') counts.low += 1;
      else counts.normal += 1;
    }
    return counts;
  }

  get donutGradient(): string {
    const { normal, low, empty, total } = this.health;
    if (!total) return 'conic-gradient(#e2e8f0 0 100%)';
    const normalEnd = (normal / total) * 100;
    const lowEnd = normalEnd + (low / total) * 100;
    return `conic-gradient(#0f766e 0 ${normalEnd}%, #d97706 ${normalEnd}% ${lowEnd}%, #dc2626 ${lowEnd}% 100%)`;
  }

  get productRows(): ProductRow[] {
    const rows = new Map<number, ProductRow>();
    const touch = (productId: number): ProductRow => {
      let row = rows.get(productId);
      if (!row) {
        const product = this.products.find((item) => item.id === productId);
        const category = this.categories.find((item) => item.id === product?.categoryId);
        row = {
          productId,
          name: product?.name || `Produit #${productId}`,
          category: category?.name || '—',
          exits: 0,
          entries: 0,
          net: 0,
          stock: 0,
        };
        rows.set(productId, row);
      }
      return row;
    };

    for (const stock of this.scopedStocks) {
      touch(stock.productId).stock += stock.quantity;
    }
    for (const movement of this.scopedMovements) {
      if (movement.productId == null) continue;
      const row = touch(movement.productId);
      if (movement.type === 'EXIT') row.exits += movement.quantity;
      else if (movement.type === 'ENTRY') row.entries += movement.quantity;
    }
    for (const row of rows.values()) row.net = row.entries - row.exits;
    return [...rows.values()].sort((a, b) => b.exits - a.exits || b.stock - a.stock || a.name.localeCompare(b.name, 'fr'));
  }

  get consumptionBars(): Array<{ name: string; qty: number; width: number }> {
    const rows = this.productRows.filter((row) => row.exits > 0).slice(0, 6);
    const max = rows.reduce((peak, row) => Math.max(peak, row.exits), 0);
    return rows.map((row) => ({
      name: row.name,
      qty: row.exits,
      width: max > 0 ? Math.max(8, Math.round((row.exits / max) * 100)) : 0,
    }));
  }

  get activity(): ActivityBucket[] {
    const byDay = new Map<string, { entry: number; exit: number }>();
    for (const movement of this.scopedMovements) {
      if (movement.type !== 'ENTRY' && movement.type !== 'EXIT') continue;
      const day = (movement.createdAt || '').slice(0, 10);
      if (!day) continue;
      const bucket = byDay.get(day) || { entry: 0, exit: 0 };
      if (movement.type === 'ENTRY') bucket.entry += movement.quantity;
      else bucket.exit += movement.quantity;
      byDay.set(day, bucket);
    }
    const days = [...byDay.keys()].sort();
    const weekly = days.length > 14;
    const grouped = new Map<string, ActivityBucket>();
    for (const day of days) {
      const values = byDay.get(day)!;
      const key = weekly ? this.weekStart(day) : day;
      const current = grouped.get(key) || {
        key,
        label: weekly ? `Sem. ${this.shortDate(key)}` : this.shortDate(day),
        entry: 0,
        exit: 0,
      };
      current.entry += values.entry;
      current.exit += values.exit;
      grouped.set(key, current);
    }
    return [...grouped.values()];
  }

  get activityMax(): number {
    return this.activity.reduce((peak, bucket) => Math.max(peak, bucket.entry, bucket.exit), 0);
  }

  get siteRows(): SiteRow[] {
    const rows = new Map<number, SiteRow>();
    const touch = (siteId: number): SiteRow => {
      let row = rows.get(siteId);
      if (!row) {
        row = {
          id: siteId,
          name: this.sites.find((site) => site.id === siteId)?.name || `Site #${siteId}`,
          units: 0,
          low: 0,
          empty: 0,
          entries: 0,
          exits: 0,
        };
        rows.set(siteId, row);
      }
      return row;
    };
    for (const stock of this.scopedStocks) {
      const row = touch(stock.siteId);
      row.units += stock.quantity;
      if (stock.status === 'LOW_STOCK') row.low += 1;
      if (stock.status === 'OUT_OF_STOCK') row.empty += 1;
    }
    for (const movement of this.scopedMovements) {
      if (movement.siteId == null) continue;
      const row = touch(movement.siteId);
      if (movement.type === 'ENTRY') row.entries += movement.quantity;
      if (movement.type === 'EXIT') row.exits += movement.quantity;
    }
    return [...rows.values()].sort((a, b) => b.exits - a.exits || b.units - a.units);
  }

  get insight(): string {
    const top = this.productRows.find((row) => row.exits > 0);
    const trend = this.trendText();
    if (!top && this.scopedMovements.length === 0) {
      return 'Aucun mouvement sur cette sélection. Élargissez la période ou retirez un filtre.';
    }
    if (!top) {
      return trend || 'Des mouvements sont enregistrés, sans sortie sur cette sélection.';
    }
    const lead = `${top.name} est le produit le plus consommé : ${this.format(top.exits)} unité(s) sortie(s).`;
    return trend ? `${lead} ${trend}` : lead;
  }

  get periodLabel(): string {
    if (this.period === 'custom') {
      if (this.fromDate || this.toDate) {
        const from = this.fromDate ? this.shortDate(this.fromDate) : '…';
        const to = this.toDate ? this.shortDate(this.toDate) : '…';
        return `${from} → ${to}`;
      }
      return 'Personnalisé';
    }
    return this.periods.find((item) => item.id === this.period)?.label || 'Période';
  }

  get activeFilterCount(): number {
    let count = 0;
    if (this.period !== '30') count += 1;
    if (this.clientId) count += 1;
    if (this.siteIds.length) count += 1;
    if (this.categoryId) count += 1;
    if (this.productId) count += 1;
    if (this.query.trim()) count += 1;
    if (this.stockStatus !== 'ALL') count += 1;
    if (this.flow !== 'ALL') count += 1;
    return count;
  }

  load(): void {
    this.loading = true;
    this.error = null;
    forkJoin({
      sites: this.catalog.listSites().pipe(catchError(() => of([] as Site[]))),
      categories: this.catalog.listCategories().pipe(catchError(() => of([] as Category[]))),
      products: this.catalog.listProducts().pipe(catchError(() => of([] as Product[]))),
      stocks: this.stockApi.listAll().pipe(catchError(() => of([] as ClientStock[]))),
      alerts: this.stockApi.alerts().pipe(catchError(() => of([] as Alert[]))),
      pending: this.stockApi.proposals('PENDING').pipe(catchError(() => of([] as DeliveryProposal[]))),
      confirmed: this.stockApi.proposals('CONFIRMED').pipe(catchError(() => of([] as DeliveryProposal[]))),
      rejected: this.stockApi.proposals('REJECTED').pipe(catchError(() => of([] as DeliveryProposal[]))),
      movements: this.collectMovements(),
    }).subscribe({
      next: (data) => {
        this.sites = data.sites;
        this.categories = data.categories;
        this.products = data.products;
        this.stocks = data.stocks;
        this.alerts = data.alerts;
        this.proposals = [...data.pending, ...data.confirmed, ...data.rejected];
        this.movements = data.movements;
        this.loading = false;
      },
      error: () => {
        this.loading = false;
        this.error = 'Impossible de charger les données d’analyse.';
      },
    });
  }

  setPeriod(period: PeriodKey): void {
    this.period = period;
    if (period === 'all' || period === 'custom') {
      if (period === 'all') {
        this.fromDate = '';
        this.toDate = '';
      }
      return;
    }
    const end = new Date();
    const start = new Date();
    if (period === 'year') start.setMonth(0, 1);
    else start.setDate(end.getDate() - (Number(period) - 1));
    this.fromDate = this.iso(start);
    this.toDate = this.iso(end);
  }

  onFrom(value: string): void {
    this.fromDate = value || '';
    this.period = 'custom';
  }

  onTo(value: string): void {
    this.toDate = value || '';
    this.period = 'custom';
  }

  onClient(value: number | string | null): void {
    this.clientId = this.asId(value);
    if (this.clientId) {
      const allowed = new Set(this.visibleSites.map((site) => site.id));
      this.siteIds = this.siteIds.filter((id) => allowed.has(id));
    }
  }

  toggleSite(siteId: number): void {
    this.siteIds = this.siteIds.includes(siteId)
      ? this.siteIds.filter((id) => id !== siteId)
      : [...this.siteIds, siteId];
  }

  onCategory(value: number | string | null): void {
    this.categoryId = this.asId(value);
    if (this.productId && !this.productChoices.some((product) => product.id === this.productId)) {
      this.productId = null;
    }
  }

  onProduct(value: number | string | null): void {
    this.productId = this.asId(value);
  }

  setStatus(status: StatusFilter): void {
    this.stockStatus = this.stockStatus === status && status !== 'ALL' ? 'ALL' : status;
  }

  reset(): void {
    this.clientId = null;
    this.siteIds = [];
    this.categoryId = null;
    this.productId = null;
    this.query = '';
    this.stockStatus = 'ALL';
    this.flow = 'ALL';
    this.setPeriod('30');
  }

  barHeight(qty: number): number {
    if (!qty || !this.activityMax) return 0;
    return Math.max(8, Math.round((qty / this.activityMax) * 100));
  }

  format(value: number): string {
    return value.toLocaleString('fr-FR');
  }

  signed(value: number): string {
    if (value > 0) return `+${this.format(value)}`;
    return this.format(value);
  }

  private collectMovements(): Observable<StockMovement[]> {
    const pageSize = 200;
    return this.stockApi.movements({ page: 1, perPage: pageSize }).pipe(
      catchError(() => of({ items: [] as StockMovement[], total: 0 })),
      switchMap((first) => {
        const pages = Math.ceil(first.total / pageSize);
        if (pages <= 1) return of(first.items);
        const rest = [];
        for (let page = 2; page <= pages; page++) {
          rest.push(
            this.stockApi.movements({ page, perPage: pageSize }).pipe(
              catchError(() => of({ items: [] as StockMovement[], total: 0 }))
            )
          );
        }
        return forkJoin(rest).pipe(
          map((pages) => pages.reduce((all, page) => all.concat(page.items), first.items.slice()))
        );
      })
    );
  }

  private stockMatches(stock: ClientStock, includeStatus: boolean): boolean {
    if (!this.siteAllowed(stock.siteId)) return false;
    if (!this.productMatches(stock.productId, stock.productName)) return false;
    if (includeStatus && this.stockStatus !== 'ALL' && (stock.status || 'NORMAL') !== this.stockStatus) {
      return false;
    }
    return true;
  }

  private movementMatches(movement: StockMovement): boolean {
    if (!this.inRange(movement.createdAt)) return false;
    if (this.flow !== 'ALL' && movement.type !== this.flow) return false;
    if (movement.siteId != null && !this.siteAllowed(movement.siteId)) return false;
    if (!this.productMatches(movement.productId, movement.productName)) return false;
    return this.statusMatches(movement.siteId, movement.productId);
  }

  private alertMatches(alert: Alert, usePeriod: boolean): boolean {
    if (usePeriod && !this.inRange(alert.createdAt)) return false;
    if (!this.siteAllowed(alert.siteId)) return false;
    if (!this.productMatches(alert.productId, alert.productName)) return false;
    return this.statusMatches(alert.siteId, alert.productId);
  }

  private proposalMatches(proposal: DeliveryProposal): boolean {
    if (!this.inRange(proposal.decidedAt || proposal.createdAt)) return false;
    if (!this.siteAllowed(proposal.siteId)) return false;
    if (!this.productMatches(proposal.productId, proposal.productName)) return false;
    return this.statusMatches(proposal.siteId, proposal.productId);
  }

  private statusMatches(siteId?: number | null, productId?: number | null): boolean {
    if (this.stockStatus === 'ALL') return true;
    const stock = this.stocks.find((item) => item.siteId === siteId && item.productId === productId);
    return !!stock && (stock.status || 'NORMAL') === this.stockStatus;
  }

  private siteAllowed(siteId: number | null | undefined): boolean {
    if (siteId == null) return !this.clientId && this.siteIds.length === 0;
    if (this.siteIds.length && !this.siteIds.includes(siteId)) return false;
    if (!this.clientId) return true;
    return this.sites.some((site) => site.id === siteId && site.clientId === this.clientId);
  }

  private productMatches(productId?: number | null, fallbackName?: string | null): boolean {
    const query = this.query.trim().toLowerCase();
    if (!this.productId && !this.categoryId && !query) return true;
    if (productId == null) return false;
    if (this.productId && productId !== this.productId) return false;
    const product = this.products.find((item) => item.id === productId);
    if (this.categoryId && product?.categoryId !== this.categoryId) return false;
    if (!query) return true;
    const name = `${product?.name || fallbackName || ''} ${product?.reference || ''}`.toLowerCase();
    return name.includes(query);
  }

  private sumType(type: MovementType): number {
    return this.scopedMovements
      .filter((movement) => movement.type === type)
      .reduce((sum, movement) => sum + movement.quantity, 0);
  }

  private trendText(): string | null {
    if (!this.fromDate || !this.toDate) return null;
    const exits = this.scopedMovements.filter((movement) => movement.type === 'EXIT');
    if (exits.length < 2) return null;
    const mid = this.midpoint(this.fromDate, this.toDate);
    let before = 0;
    let after = 0;
    for (const movement of exits) {
      const day = (movement.createdAt || '').slice(0, 10);
      if (day < mid) before += movement.quantity;
      else after += movement.quantity;
    }
    if (before === 0 && after === 0) return null;
    if (before === 0) return 'Les sorties sont concentrées sur la fin de la période.';
    const delta = Math.round(((after - before) / before) * 100);
    if (delta === 0) return 'Les sorties sont stables entre le début et la fin de la période.';
    if (delta > 0) return `Les sorties augmentent de ${delta} % sur la seconde moitié de la période.`;
    return `Les sorties baissent de ${Math.abs(delta)} % sur la seconde moitié de la période.`;
  }

  private inRange(value?: string | null): boolean {
    if (!this.fromDate && !this.toDate) return true;
    if (!value) return false;
    const day = value.slice(0, 10);
    if (this.fromDate && day < this.fromDate) return false;
    if (this.toDate && day > this.toDate) return false;
    return true;
  }

  private midpoint(from: string, to: string): string {
    const start = new Date(`${from}T00:00:00`).getTime();
    const end = new Date(`${to}T00:00:00`).getTime();
    return this.iso(new Date(start + (end - start) / 2));
  }

  private weekStart(day: string): string {
    const date = new Date(`${day}T00:00:00`);
    const offset = (date.getDay() + 6) % 7;
    date.setDate(date.getDate() - offset);
    return this.iso(date);
  }

  private shortDate(day: string): string {
    const [, month, date] = day.split('-');
    return `${date}/${month}`;
  }

  private asId(value: number | string | null): number | null {
    if (value == null || value === '') return null;
    const id = Number(value);
    return Number.isFinite(id) ? id : null;
  }

  private iso(date: Date): string {
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${date.getFullYear()}-${month}-${day}`;
  }
}
