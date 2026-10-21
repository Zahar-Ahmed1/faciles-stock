import { Component, OnDestroy, OnInit } from '@angular/core';
import { FormBuilder, FormGroup, Validators } from '@angular/forms';
import { Router } from '@angular/router';
import {
  catchError,
  finalize,
  of,
  Subject,
  takeUntil,
  tap,
} from 'rxjs';
import { environment } from '../../../environments/environment';
import { AuthService } from '../../services/auth.service';
import { StockService } from '../../services/stock.service';
import { CatalogService } from '../../services/catalog.service';
import { ClientStock, DeliveryProposal, StockStatus } from '../../models/stock.model';

type StockFilter = 'ALL' | StockStatus;

@Component({
  selector: 'app-responsable',
  templateUrl: './responsable.component.html',
  styleUrls: ['./responsable.component.scss'],
})
export class ResponsableComponent implements OnInit, OnDestroy {
  stocks: ClientStock[] = [];
  proposals: DeliveryProposal[] = [];
  proposalBusy = new Set<number>();
  loading = false;
  error: string | null = null;
  siteName: string | null = null;
  busyIds = new Map<number, 'decrement' | 'increment'>();
  /** Quantité à retirer choisie par le responsable, par stockId */
  removeQty: Record<number, number> = {};
  toast: { text: string; type: 'success' | 'error' | 'info' } | null = null;
  query = '';
  statusFilter: StockFilter = 'ALL';

  showForm = false;
  editing: ClientStock | null = null;
  imageFile: File | null = null;
  imagePreview: string | null = null;
  form: FormGroup;
  saving = false;

  private readonly destroy$ = new Subject<void>();
  private toastTimer: ReturnType<typeof setTimeout> | null = null;

  constructor(
    readonly auth: AuthService,
    private readonly stockService: StockService,
    private readonly catalog: CatalogService,
    private readonly fb: FormBuilder,
    private readonly router: Router
  ) {
    this.form = this.fb.group({
      name: ['', [Validators.required, Validators.minLength(2)]],
      quantity: [0, [Validators.required, Validators.min(0)]],
      minimumQuantity: [10, [Validators.required, Validators.min(0)]],
    });
  }

  get siteId(): number | null {
    return this.auth.currentUser?.siteId ?? null;
  }

  get lowCount(): number {
    return this.stocks.filter((s) => this.statusOf(s) !== 'NORMAL').length;
  }

  get okCount(): number {
    return this.stocks.filter((s) => this.statusOf(s) === 'NORMAL').length;
  }

  get lowOnlyCount(): number {
    return this.stocks.filter((s) => this.statusOf(s) === 'LOW_STOCK').length;
  }

  get emptyCount(): number {
    return this.stocks.filter((s) => this.statusOf(s) === 'OUT_OF_STOCK').length;
  }

  get visibleStocks(): ClientStock[] {
    const q = this.normalize(this.query);
    return this.stocks
      .filter((stock) => {
        if (this.statusFilter !== 'ALL' && this.statusOf(stock) !== this.statusFilter) {
          return false;
        }
        if (!q) return true;
        return this.normalize(stock.productName || '').includes(q);
      })
      .sort((a, b) => {
        const byStatus = this.statusRank(this.statusOf(a)) - this.statusRank(this.statusOf(b));
        if (byStatus !== 0) return byStatus;
        return (b.quantity ?? 0) - (a.quantity ?? 0);
      });
  }

  private statusRank(status: StockStatus): number {
    if (status === 'OUT_OF_STOCK') return 0;
    if (status === 'LOW_STOCK') return 1;
    return 2;
  }

  setFilter(filter: StockFilter): void {
    this.statusFilter = filter;
  }

  clearSearch(): void {
    this.query = '';
    this.statusFilter = 'ALL';
  }

  ngOnInit(): void {
    this.loadSiteName();
    this.load();
  }

  private loadSiteName(): void {
    const siteId = this.siteId;
    if (!siteId) return;
    this.catalog
      .getSite(siteId)
      .pipe(
        takeUntil(this.destroy$),
        catchError(() => of(null))
      )
      .subscribe((site) => {
        this.siteName = site?.name ?? `Site #${siteId}`;
      });
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    if (this.toastTimer) clearTimeout(this.toastTimer);
  }

  load(): void {
    const siteId = this.siteId;
    if (!siteId) {
      this.error = 'Aucun site assigné. Contactez Facilyx.';
      return;
    }
    this.loading = true;
    this.error = null;
    this.stockService
      .listBySite(siteId)
      .pipe(
        takeUntil(this.destroy$),
        catchError((err) => {
          this.error =
            err?.error?.error?.message || err?.message || 'Erreur de chargement';
          return of([] as ClientStock[]);
        }),
        finalize(() => (this.loading = false))
      )
      .subscribe((list) => {
        this.stocks = list;
        this.loadProposals();
        for (const s of list) {
          if (this.removeQty[s.id] == null) {
            this.removeQty[s.id] = 1;
          } else {
            this.removeQty[s.id] = Math.min(
              Math.max(1, this.removeQty[s.id]),
              Math.max(1, s.quantity)
            );
          }
        }
      });
  }

  private loadProposals(): void {
    this.stockService
      .proposals()
      .pipe(
        takeUntil(this.destroy$),
        catchError(() => of([] as DeliveryProposal[]))
      )
      .subscribe((list) => {
        this.proposals = list.filter((p) => p.status === 'PENDING');
      });
  }

  confirmProposal(proposal: DeliveryProposal): void {
    if (this.proposalBusy.has(proposal.id)) return;
    this.proposalBusy.add(proposal.id);
    this.stockService
      .confirmProposal(proposal.id)
      .pipe(
        takeUntil(this.destroy$),
        catchError((err) => {
          this.showToast(
            err?.error?.error?.message || err?.message || 'Confirmation impossible',
            'error'
          );
          return of(null);
        }),
        finalize(() => this.proposalBusy.delete(proposal.id))
      )
      .subscribe((res) => {
        if (!res) return;
        if (res.delivery) {
          const added = res.delivery.quantity - res.delivery.oldQuantity;
          this.showToast(`Ajout confirmé : +${added}`, 'success');
        } else {
          this.showToast('Confirmation enregistrée. En attente du manager du site.', 'info');
        }
        this.load();
      });
  }

  rejectProposal(proposal: DeliveryProposal): void {
    if (this.proposalBusy.has(proposal.id)) return;
    this.proposalBusy.add(proposal.id);
    this.stockService
      .rejectProposal(proposal.id)
      .pipe(
        takeUntil(this.destroy$),
        catchError((err) => {
          this.showToast(
            err?.error?.error?.message || err?.message || 'Refus impossible',
            'error'
          );
          return of(null);
        }),
        finalize(() => this.proposalBusy.delete(proposal.id))
      )
      .subscribe((res) => {
        if (!res) return;
        this.showToast('Proposition refusée', 'info');
        this.proposals = this.proposals.filter((p) => p.id !== proposal.id);
      });
  }

  qtyToRemove(stock: ClientStock): number {
    const q = this.removeQty[stock.id] ?? 1;
    return Math.min(Math.max(1, Math.floor(q) || 1), Math.max(1, stock.quantity));
  }

  setQtyToRemove(stock: ClientStock, value: number | string): void {
    const n = typeof value === 'string' ? parseInt(value, 10) : value;
    if (!Number.isFinite(n) || n < 1) {
      this.removeQty[stock.id] = 1;
      return;
    }
    this.removeQty[stock.id] = Math.min(Math.floor(n), Math.max(1, stock.quantity));
  }

  bumpQty(stock: ClientStock, delta: number): void {
    this.setQtyToRemove(stock, this.qtyToRemove(stock) + delta);
  }

  statusOf(stock: ClientStock): StockStatus {
    if (stock.status) return stock.status;
    const q = stock.quantity ?? 0;
    const min = stock.minimumQuantity ?? 0;
    if (q <= 0) return 'OUT_OF_STOCK';
    if (q <= min) return 'LOW_STOCK';
    return 'NORMAL';
  }

  statusLabel(stock: ClientStock): string {
    switch (this.statusOf(stock)) {
      case 'OUT_OF_STOCK':
        return 'Vide';
      case 'LOW_STOCK':
        return 'Stock bas';
      default:
        return 'En stock';
    }
  }

  private normalize(value: string): string {
    return value
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .toLowerCase()
      .trim();
  }

  decrement(stock: ClientStock): void {
    const qty = this.qtyToRemove(stock);
    if (this.busyIds.has(stock.id) || stock.quantity < qty || qty < 1) return;
    this.busyIds.set(stock.id, 'decrement');
    this.stockService
      .decrement(stock.id, qty)
      .pipe(
        takeUntil(this.destroy$),
        catchError((err) => {
          this.showToast(
            err?.error?.error?.message || err?.message || 'Impossible',
            'error'
          );
          return of(null);
        }),
        finalize(() => this.busyIds.delete(stock.id))
      )
      .subscribe((res) => {
        if (!res) return;
        stock.quantity = res.quantity;
        stock.status = res.status;
        this.removeQty[stock.id] = Math.min(
          this.qtyToRemove(stock),
          Math.max(1, stock.quantity || 1)
        );
        if (res.alertCreated) {
          this.showToast('Facilyx a été prévenu — livraison bientôt', 'info');
        }
      });
  }

  imageSrc(url?: string | null): string | null {
    if (!url) return null;
    if (url.startsWith('/uploads')) {
      return environment.production ? url : `${environment.apiUrl}${url}`;
    }
    return url;
  }

  onImageSelected(event: Event): void {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    input.value = '';
    if (!file) return;
    const type = file.type.toLowerCase();
    const name = file.name.toLowerCase();
    const allowed =
      type === 'image/png' ||
      type === 'image/jpeg' ||
      type === 'image/jpg' ||
      type === 'image/webp' ||
      name.endsWith('.png') ||
      name.endsWith('.jpg') ||
      name.endsWith('.jpeg') ||
      name.endsWith('.webp');
    if (!allowed) {
      this.showToast('Utilise l’appareil photo ou un fichier PNG', 'error');
      return;
    }
    this.imageFile = file;
    const reader = new FileReader();
    reader.onload = () => {
      this.imagePreview = typeof reader.result === 'string' ? reader.result : null;
    };
    reader.readAsDataURL(file);
  }

  openCreate(): void {
    this.editing = null;
    this.imageFile = null;
    this.imagePreview = null;
    this.form.reset({ name: '', quantity: 0, minimumQuantity: 10 });
    this.showForm = true;
  }

  openEdit(stock: ClientStock): void {
    this.editing = stock;
    this.imageFile = null;
    this.imagePreview = this.imageSrc(stock.productImageUrl);
    this.form.reset({
      name: stock.productName || '',
      quantity: stock.quantity,
      minimumQuantity: stock.minimumQuantity,
    });
    this.showForm = true;
  }

  closeForm(): void {
    this.showForm = false;
    this.editing = null;
    this.imageFile = null;
    this.imagePreview = null;
  }

  saveProduct(): void {
    if (this.form.invalid || this.saving) return;
    this.saving = true;
    const value = this.form.value as {
      name: string;
      quantity: number;
      minimumQuantity: number;
    };

    const req$ = this.editing
      ? this.catalog.updateProduct(this.editing.productId, value, this.imageFile)
      : this.catalog.createProduct(value, this.imageFile);

    req$
      .pipe(
        takeUntil(this.destroy$),
        catchError((err) => {
          this.showToast(
            err?.error?.error?.message || err?.message || 'Erreur enregistrement',
            'error'
          );
          return of(null);
        }),
        finalize(() => (this.saving = false))
      )
      .subscribe((res) => {
        if (!res) return;
        this.closeForm();
        this.showToast(
          this.editing ? 'Produit modifié' : 'Produit ajouté',
          'success'
        );
        this.load();
      });
  }

  deleteProduct(stock: ClientStock): void {
    if (!confirm(`Supprimer « ${stock.productName} » ?`)) return;
    this.catalog
      .deleteProduct(stock.productId)
      .pipe(
        takeUntil(this.destroy$),
        catchError((err) => {
          this.showToast(
            err?.error?.error?.message || err?.message || 'Suppression impossible',
            'error'
          );
          return of(null);
        }),
        tap(() => {
          this.showToast('Produit supprimé', 'success');
          this.load();
        })
      )
      .subscribe();
  }

  logout(): void {
    this.auth.logout();
    void this.router.navigate(['/login']);
  }

  private showToast(text: string, type: 'success' | 'error' | 'info'): void {
    this.toast = { text, type };
    if (this.toastTimer) clearTimeout(this.toastTimer);
    this.toastTimer = setTimeout(() => (this.toast = null), 2800);
  }
}
