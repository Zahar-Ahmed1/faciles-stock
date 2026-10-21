import { Component, OnInit } from '@angular/core';
import { catchError, finalize, forkJoin, of } from 'rxjs';
import { AuthService } from '../../services/auth.service';
import { CatalogService } from '../../services/catalog.service';
import { StockService } from '../../services/stock.service';
import { UserService } from '../../services/user.service';
import { Site } from '../../models/catalog.model';
import { Alert, ClientStock, DeliveryProposal, StockMovement } from '../../models/stock.model';
import { User, UserRole } from '../../models/user.model';

interface SiteStat {
  site: Site;
  responsables: User[];
  products: number;
  ok: number;
  low: number;
  empty: number;
  openAlerts: number;
  pending: number;
  lastAlert?: string;
}

@Component({
  selector: 'app-admin-dashboard',
  templateUrl: './admin-dashboard.component.html',
  styleUrls: ['./admin-dashboard.component.scss'],
})
export class AdminDashboardComponent implements OnInit {
  loading = false;
  saving = false;
  error: string | null = null;
  toast: string | null = null;

  sites: Site[] = [];
  stocks: ClientStock[] = [];
  alerts: Alert[] = [];
  proposals: DeliveryProposal[] = [];
  movements: StockMovement[] = [];
  users: User[] = [];

  selectedSiteId: number | null = null;
  openSections: Record<string, boolean> = {
    sites: true,
    deliveries: true,
    alerts: true,
    movements: true,
    responsables: true,
    managers: true,
  };
  proposalBusy = new Set<number>();
  formOpen = false;
  editingId: number | null = null;
  deleteTarget: User | null = null;
  role: UserRole = 'STOCK_MANAGER';
  firstName = '';
  lastName = '';
  email = '';
  password = '';
  siteId: number | null = null;

  constructor(
    readonly auth: AuthService,
    private readonly catalog: CatalogService,
    private readonly stockApi: StockService,
    private readonly usersApi: UserService
  ) {
    if (this.auth.hasRole('MANAGER')) {
      this.selectedSiteId = this.auth.currentUser?.siteId ?? null;
    }
  }

  ngOnInit(): void {
    this.load();
  }

  get canManageAccounts(): boolean {
    return this.auth.hasRole('ADMIN');
  }

  get managers(): User[] {
    return this.users.filter((user) => user.role === 'MANAGER');
  }

  get responsables(): User[] {
    return this.users.filter((user) => user.role === 'STOCK_MANAGER');
  }

  get visibleStocks(): ClientStock[] {
    return this.stocks.filter((stock) => this.matchesScope(stock.siteId));
  }

  get productCount(): number {
    return this.visibleStocks.length;
  }

  get lowCount(): number {
    return this.visibleStocks.filter((stock) => stock.status === 'LOW_STOCK').length;
  }

  get emptyCount(): number {
    return this.visibleStocks.filter((stock) => stock.status === 'OUT_OF_STOCK').length;
  }

  get pendingProposals(): DeliveryProposal[] {
    return this.proposals.filter(
      (proposal) =>
        proposal.status === 'PENDING' && this.matchesScope(proposal.siteId)
    );
  }

  get allOpenAlerts(): Alert[] {
    return this.alerts
      .filter((alert) => alert.status !== 'RESOLVED')
      .sort((a, b) => (a.createdAt < b.createdAt ? 1 : -1));
  }

  get openAlerts(): Alert[] {
    return this.allOpenAlerts.filter(
      (alert) => this.matchesScope(alert.siteId)
    );
  }

  get selectedSiteName(): string {
    return this.sites.find((site) => site.id === this.selectedSiteId)?.name || '';
  }

  get visibleMovements(): StockMovement[] {
    return this.movements.filter(
      (move) => this.matchesScope(move.siteId)
    );
  }

  selectSite(siteId: number): void {
    if (this.auth.hasRole('MANAGER')) return;
    this.selectedSiteId = this.selectedSiteId === siteId ? null : siteId;
  }

  clearSite(): void {
    if (this.auth.hasRole('MANAGER')) return;
    this.selectedSiteId = null;
  }

  get siteStats(): SiteStat[] {
    const sites = this.auth.hasRole('MANAGER')
      ? this.sites.filter((site) => site.id === this.auth.currentUser?.siteId)
      : this.sites;
    return sites
      .map((site) => {
        const rows = this.stocks.filter((stock) => stock.siteId === site.id);
        const siteAlerts = this.alerts.filter((alert) => alert.siteId === site.id);
        const open = siteAlerts.filter((alert) => alert.status !== 'RESOLVED');
        const last = [...open].sort((a, b) => (a.createdAt < b.createdAt ? 1 : -1))[0];
        return {
          site,
          responsables: this.responsables.filter((user) => user.siteId === site.id),
          products: rows.length,
          ok: rows.filter((stock) => stock.status === 'NORMAL' || !stock.status).length,
          low: rows.filter((stock) => stock.status === 'LOW_STOCK').length,
          empty: rows.filter((stock) => stock.status === 'OUT_OF_STOCK').length,
          openAlerts: open.length,
          pending: this.proposals.filter(
            (proposal) => proposal.status === 'PENDING' && proposal.siteId === site.id
          ).length,
          lastAlert: last?.createdAt,
        };
      })
      .sort((a, b) => b.empty + b.low - (a.empty + a.low) || a.site.name.localeCompare(b.site.name, 'fr'));
  }

  load(): void {
    this.loading = true;
    this.error = null;
    forkJoin({
      sites: this.catalog.listSites().pipe(catchError(() => of([] as Site[]))),
      stocks: this.stockApi.listAll().pipe(catchError(() => of([] as ClientStock[]))),
      alerts: this.stockApi.alerts().pipe(catchError(() => of([] as Alert[]))),
      proposals: this.stockApi.proposals().pipe(catchError(() => of([] as DeliveryProposal[]))),
      movements: this.stockApi.movements({ perPage: 40, page: 1 }).pipe(
        catchError(() => of({ items: [] as StockMovement[], total: 0 }))
      ),
      users: this.usersApi.listAll().pipe(catchError(() => of([] as User[]))),
    })
      .pipe(finalize(() => (this.loading = false)))
      .subscribe((data) => {
        this.sites = data.sites;
        this.stocks = data.stocks;
        this.alerts = data.alerts;
        this.proposals = data.proposals;
        this.movements = data.movements.items;
        this.users = data.users;
        if (!this.siteId && data.sites[0]) this.siteId = data.sites[0].id;
      });
  }

  openCreate(role: UserRole): void {
    if (!this.canManageAccounts) return;
    this.editingId = null;
    this.role = role;
    this.firstName = '';
    this.lastName = '';
    this.email = '';
    this.password = '';
    this.siteId = this.sites[0]?.id ?? null;
    this.error = null;
    this.formOpen = true;
  }

  openEdit(user: User): void {
    if (!this.canManageAccounts || (user.role !== 'MANAGER' && user.role !== 'STOCK_MANAGER')) return;
    this.editingId = user.id;
    this.role = user.role;
    this.firstName = user.firstName;
    this.lastName = user.lastName;
    this.email = user.email;
    this.password = '';
    this.siteId = user.siteId ?? null;
    this.error = null;
    this.formOpen = true;
  }

  closeForm(): void {
    if (this.saving) return;
    this.formOpen = false;
    this.editingId = null;
  }

  isOpen(section: string): boolean {
    return this.openSections[section] !== false;
  }

  toggleSection(section: string): void {
    this.openSections[section] = !this.isOpen(section);
  }

  askDelete(user: User): void {
    if (!this.canManageAccounts || (user.role !== 'MANAGER' && user.role !== 'STOCK_MANAGER')) return;
    this.error = null;
    this.deleteTarget = user;
    this.openSections[user.role === 'MANAGER' ? 'managers' : 'responsables'] = true;
  }

  cancelDelete(): void {
    if (this.saving) return;
    this.deleteTarget = null;
  }

  confirmDelete(): void {
    if (!this.deleteTarget || !this.canManageAccounts) return;
    const user = this.deleteTarget;
    this.saving = true;
    this.error = null;
    this.usersApi
      .remove(user.id)
      .pipe(finalize(() => (this.saving = false)))
      .subscribe({
        next: () => {
          this.deleteTarget = null;
          this.toast = `${user.firstName} ${user.lastName} a été supprimé.`;
          this.load();
        },
        error: (err) => {
          this.error = this.message(err, 'Suppression impossible.');
        },
      });
  }

  saveAccount(): void {
    if (!this.canManageAccounts) return;
    if (this.firstName.trim().length < 2 || this.lastName.trim().length < 2) {
      this.error = 'Indiquez le prénom et le nom.';
      return;
    }
    if (!this.email.includes('@')) {
      this.error = 'Indiquez un e-mail valide.';
      return;
    }
    const password = this.password.trim();
    if (!this.editingId && password.length < 6) {
      this.error = 'Le mot de passe doit contenir au moins 6 caractères.';
      return;
    }
    if (this.editingId && password.length > 0 && password.length < 6) {
      this.error = 'Le nouveau mot de passe doit contenir au moins 6 caractères.';
      return;
    }
    if ((this.role === 'STOCK_MANAGER' || this.role === 'MANAGER') && !this.siteId) {
      this.error = 'Choisissez le site de ce compte.';
      return;
    }
    this.saving = true;
    this.error = null;
    const payload = {
      firstName: this.firstName.trim(),
      lastName: this.lastName.trim(),
      email: this.email.trim(),
      ...(password ? { password } : {}),
      ...(this.editingId
        ? { siteId: this.siteId }
        : {
            role: this.role,
            siteId: this.role === 'ADMIN' ? null : this.siteId,
            active: true,
          }),
    };
    const request = this.editingId
      ? this.usersApi.update(this.editingId, payload)
      : this.usersApi.create(payload);
    request.pipe(finalize(() => (this.saving = false))).subscribe({
      next: () => {
        const edited = this.editingId !== null;
        this.formOpen = false;
        this.editingId = null;
        this.toast = edited ? 'Compte modifié.' : 'Compte créé.';
        this.load();
      },
      error: (err) => {
        this.error = this.message(err, this.editingId ? 'Modification impossible.' : 'Création impossible.');
      },
    });
  }

  siteName(siteId?: number | null): string {
    return this.sites.find((site) => site.id === siteId)?.name || 'Aucun site';
  }

  roleTitle(editing: boolean): string {
    const who = this.role === 'STOCK_MANAGER' ? 'responsable' : 'manager';
    return editing ? `Modifier le ${who}` : `Nouveau ${who}`;
  }

  initials(user: User): string {
    return `${user.firstName?.[0] ?? ''}${user.lastName?.[0] ?? ''}`.toUpperCase();
  }

  formatDate(value?: string): string {
    if (!value) return '—';
    const date = new Date(value.includes('T') ? value : value.replace(' ', 'T'));
    if (Number.isNaN(date.getTime())) return value;
    return date.toLocaleString('fr-FR', { dateStyle: 'medium', timeStyle: 'short' });
  }

  confirmationLine(proposal: DeliveryProposal): string {
    const manager = proposal.managerConfirmedBy
      ? `Manager ${proposal.managerConfirmedByName || 'confirmé'}`
      : 'Manager en attente';
    const responsable = proposal.responsableConfirmedBy
      ? `Responsable ${proposal.responsableConfirmedByName || 'confirmé'}`
      : 'Responsable en attente';
    return `${manager} · ${responsable}`;
  }

  confirmDelivery(proposal: DeliveryProposal): void {
    this.decideDelivery(proposal, true);
  }

  refuseDelivery(proposal: DeliveryProposal): void {
    this.decideDelivery(proposal, false);
  }

  movementLabel(type: string): string {
    if (type === 'ENTRY') return 'Entrée';
    if (type === 'EXIT') return 'Sortie';
    return 'Ajustement';
  }

  private decideDelivery(proposal: DeliveryProposal, confirm: boolean): void {
    if (!this.auth.hasRole('MANAGER') || this.proposalBusy.has(proposal.id)) return;
    this.proposalBusy.add(proposal.id);
    this.error = null;
    const request = confirm
      ? this.stockApi.confirmProposal(proposal.id)
      : this.stockApi.rejectProposal(proposal.id);
    request.pipe(finalize(() => this.proposalBusy.delete(proposal.id))).subscribe({
      next: (res) => {
        this.toast = res.delivery
          ? 'Les deux confirmations sont enregistrées. Le stock a augmenté.'
          : confirm
            ? 'Confirmation enregistrée. En attente du responsable.'
            : 'Livraison refusée.';
        this.load();
      },
      error: (err) => {
        this.error = this.message(err, confirm ? 'Confirmation impossible.' : 'Refus impossible.');
      },
    });
  }

  private matchesScope(siteId: number | null | undefined): boolean {
    if (this.auth.hasRole('MANAGER')) {
      const own = this.auth.currentUser?.siteId ?? null;
      return own != null && siteId === own;
    }
    return !this.selectedSiteId || siteId === this.selectedSiteId;
  }

  private message(err: unknown, fallback: string): string {
    const body = err as { error?: { error?: { message?: string } }; message?: string };
    return body?.error?.error?.message || body?.message || fallback;
  }
}
