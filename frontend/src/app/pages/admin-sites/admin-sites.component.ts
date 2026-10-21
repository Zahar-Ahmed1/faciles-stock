import { Component, OnInit } from '@angular/core';
import { catchError, finalize, forkJoin, map, Observable, of, switchMap } from 'rxjs';
import { CatalogService } from '../../services/catalog.service';
import { AuthService } from '../../services/auth.service';
import { UserService } from '../../services/user.service';
import { Client, Site } from '../../models/catalog.model';
import { User } from '../../models/user.model';

@Component({
  selector: 'app-admin-sites',
  templateUrl: './admin-sites.component.html',
  styleUrls: ['./admin-sites.component.scss'],
})
export class AdminSitesComponent implements OnInit {
  sites: Site[] = [];
  clients: Client[] = [];
  accounts: User[] = [];
  query = '';
  loading = false;
  saving = false;
  error: string | null = null;
  toast: string | null = null;
  formOpen = false;
  editing: Site | null = null;
  pendingDelete: Site | null = null;

  clientMode: 'existing' | 'new' = 'existing';
  clientId: number | null = null;
  newClientName = '';
  name = '';
  city = '';
  address = '';
  code = '';

  accountMode: 'existing' | 'new' | 'none' = 'existing';
  accountUserId: number | null = null;
  accountFirstName = '';
  accountLastName = '';
  accountEmail = '';
  accountPassword = '';

  constructor(
    private readonly catalog: CatalogService,
    private readonly usersApi: UserService,
    readonly auth: AuthService
  ) {}

  ngOnInit(): void {
    this.load();
  }

  get canDelete(): boolean {
    return this.auth.hasRole('ADMIN');
  }

  get canAssignAccount(): boolean {
    return this.auth.hasRole('ADMIN');
  }

  get visibleSites(): Site[] {
    const q = this.normalize(this.query);
    const list = q
      ? this.sites.filter((site) =>
          [
            site.name,
            site.clientName,
            site.city,
            site.address,
            site.code,
            ...this.accountsFor(site).map((user) => `${user.firstName} ${user.lastName} ${user.email}`),
          ]
            .map((value) => this.normalize(value || ''))
            .some((value) => value.includes(q))
        )
      : this.sites;
    return [...list].sort((a, b) => a.name.localeCompare(b.name, 'fr'));
  }

  load(): void {
    this.loading = true;
    this.error = null;
    forkJoin({
      sites: this.catalog.listSites(),
      clients: this.catalog.listClients(),
      accounts: this.usersApi.listByRole('STOCK_MANAGER').pipe(catchError(() => of([] as User[]))),
    })
      .pipe(
        catchError((err) => {
          this.error = this.message(err, 'Impossible de charger les sites.');
          return of({ sites: [] as Site[], clients: [] as Client[], accounts: [] as User[] });
        }),
        finalize(() => (this.loading = false))
      )
      .subscribe(({ sites, clients, accounts }) => {
        this.sites = sites;
        this.clients = [...clients].sort((a, b) => a.name.localeCompare(b.name, 'fr'));
        this.accounts = [...accounts].sort((a, b) => a.email.localeCompare(b.email, 'fr'));
      });
  }

  accountsFor(site: Site): User[] {
    return this.accounts.filter((user) => user.siteId === site.id);
  }

  accountLabel(user: User): string {
    const site = this.sites.find((item) => item.id === user.siteId);
    const place = site ? ` · ${site.name}` : '';
    return `${user.firstName} ${user.lastName} — ${user.email}${place}`;
  }

  openCreate(): void {
    this.editing = null;
    this.clientMode = this.clients.length ? 'existing' : 'new';
    this.clientId = this.clients[0]?.id ?? null;
    this.newClientName = '';
    this.name = '';
    this.city = '';
    this.address = '';
    this.code = '';
    this.resetAccount(null);
    this.error = null;
    this.formOpen = true;
  }

  openEdit(site: Site): void {
    this.editing = site;
    this.clientMode = 'existing';
    this.clientId = site.clientId;
    this.newClientName = '';
    this.name = site.name;
    this.city = site.city || '';
    this.address = site.address || '';
    this.code = site.code || '';
    this.resetAccount(site);
    this.error = null;
    this.formOpen = true;
  }

  closeForm(): void {
    if (this.saving) return;
    this.formOpen = false;
    this.editing = null;
  }

  save(): void {
    const siteName = this.name.trim();
    if (siteName.length < 2) {
      this.error = 'Le nom du site doit contenir au moins 2 caractères.';
      return;
    }
    if (this.clientMode === 'new' && this.newClientName.trim().length < 2) {
      this.error = 'Le nom du client doit contenir au moins 2 caractères.';
      return;
    }
    if (this.clientMode === 'existing' && !this.clientId) {
      this.error = 'Choisissez un client.';
      return;
    }
    if (this.canAssignAccount && !this.accountIsValid()) {
      return;
    }

    this.saving = true;
    this.error = null;
    this.toast = null;

    const client$ =
      this.clientMode === 'new'
        ? this.createClientThenId()
        : of(this.clientId);

    client$
      .pipe(
        switchMap((clientId) => {
          if (!clientId) {
            throw new Error('Choisissez un client.');
          }
          const payload = {
            clientId,
            name: siteName,
            city: this.city.trim() || undefined,
            address: this.address.trim() || undefined,
            code: this.code.trim() || undefined,
            active: true,
          };
          const saved = this.editing
            ? this.catalog.updateSite(this.editing.id, payload)
            : this.catalog.createSite(payload);
          return saved.pipe(
            switchMap((site) =>
              this.assignAccount(site).pipe(
                catchError((err) => {
                  this.editing = site;
                  throw err;
                })
              )
            )
          );
        }),
        finalize(() => (this.saving = false))
      )
      .subscribe({
        next: () => {
          this.formOpen = false;
          this.editing = null;
          this.toast =
            this.accountMode === 'none' || !this.canAssignAccount
              ? 'Site enregistré.'
              : 'Site affecté au compte.';
          this.load();
        },
        error: (err) => {
          this.error = this.message(err, 'Enregistrement impossible.');
        },
      });
  }

  askRemove(site: Site): void {
    if (!this.canDelete) return;
    this.pendingDelete = site;
    this.toast = null;
  }

  cancelRemove(): void {
    this.pendingDelete = null;
  }

  confirmRemove(): void {
    const site = this.pendingDelete;
    if (!site || !this.canDelete) return;
    this.error = null;
    this.toast = null;
    this.catalog.deleteSite(site.id).subscribe({
      next: () => {
        this.pendingDelete = null;
        this.toast = 'Site supprimé.';
        this.load();
      },
      error: (err) => {
        this.error = this.message(err, 'Suppression impossible.');
      },
    });
  }

  private resetAccount(site: Site | null): void {
    const assigned = site ? this.accountsFor(site)[0] : undefined;
    if (assigned) {
      this.accountMode = 'existing';
      this.accountUserId = assigned.id;
    } else if (site) {
      this.accountMode = 'none';
      this.accountUserId = this.accounts[0]?.id ?? null;
    } else {
      const preferred = this.accounts.find((user) => user.email === 'stock.manager@example.com');
      this.accountMode = this.accounts.length ? 'existing' : 'new';
      this.accountUserId = preferred?.id ?? this.accounts[0]?.id ?? null;
    }
    this.accountFirstName = '';
    this.accountLastName = '';
    this.accountEmail = '';
    this.accountPassword = '';
  }

  private accountIsValid(): boolean {
    if (this.accountMode === 'none') return true;
    if (this.accountMode === 'existing') {
      if (!this.accountUserId) {
        this.error = 'Choisissez le compte du site.';
        return false;
      }
      return true;
    }
    if (this.accountFirstName.trim().length < 2 || this.accountLastName.trim().length < 2) {
      this.error = 'Le prénom et le nom du compte sont obligatoires.';
      return false;
    }
    if (!this.accountEmail.includes('@')) {
      this.error = 'Indiquez un e-mail valide pour le compte.';
      return false;
    }
    if (this.accountPassword.trim().length < 6) {
      this.error = 'Le mot de passe doit contenir au moins 6 caractères.';
      return false;
    }
    return true;
  }

  private assignAccount(site: Site): Observable<Site> {
    if (!this.canAssignAccount) {
      return of(site);
    }
    if (this.accountMode === 'none') {
      return this.clearSiteAccounts(site.id, null).pipe(map(() => site));
    }
    if (this.accountMode === 'new') {
      return this.usersApi
        .create({
          firstName: this.accountFirstName.trim(),
          lastName: this.accountLastName.trim(),
          email: this.accountEmail.trim(),
          password: this.accountPassword,
          role: 'STOCK_MANAGER',
          siteId: site.id,
          active: true,
        })
        .pipe(switchMap((created) => this.clearSiteAccounts(site.id, created.id).pipe(map(() => site))));
    }
    const userId = this.accountUserId;
    if (!userId) return of(site);
    return this.usersApi
      .update(userId, { siteId: site.id })
      .pipe(switchMap(() => this.clearSiteAccounts(site.id, userId).pipe(map(() => site))));
  }

  private clearSiteAccounts(siteId: number, keepUserId: number | null): Observable<void> {
    const others = this.accounts.filter((user) => user.siteId === siteId && user.id !== keepUserId);
    if (!others.length) return of(undefined);
    return forkJoin(others.map((user) => this.usersApi.update(user.id, { siteId: null }))).pipe(
      map(() => undefined)
    );
  }

  private createClientThenId() {
    return this.catalog.createClient({ name: this.newClientName.trim() }).pipe(
      switchMap((client) => of(client.id))
    );
  }

  private normalize(value: string): string {
    return value
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .toLowerCase()
      .trim();
  }

  private message(err: unknown, fallback: string): string {
    const body = err as { error?: { error?: { message?: string } }; message?: string };
    const apiMessage = body?.error?.error?.message;
    if (apiMessage) return apiMessage;
    if (err instanceof Error && err.message && !err.message.startsWith('Http failure')) {
      return err.message;
    }
    return fallback;
  }
}
