import { Component, Input, Output, EventEmitter } from '@angular/core';
import { ClientStock, StockStatus } from '../../models/stock.model';

@Component({
  selector: 'app-stock-card',
  templateUrl: './stock-card.component.html',
  styleUrls: ['./stock-card.component.scss'],
})
export class StockCardComponent {
  @Input({ required: true }) stock!: ClientStock;
  @Input() loadingOp: 'increment' | 'decrement' | null = null;
  @Output() decrement = new EventEmitter<number>();
  @Output() increment = new EventEmitter<number>();

  get statusClass(): string {
    return `status-${this.status.toLowerCase()}`;
  }

  get statusLabel(): string {
    switch (this.status) {
      case 'NORMAL':
        return 'Stock normal';
      case 'LOW_STOCK':
        return 'Stock faible';
      case 'OUT_OF_STOCK':
        return 'Rupture';
      default:
        return '';
    }
  }

  get canDecrement(): boolean {
    return (this.stock.quantity ?? 0) > 0 && !this.loadingOp;
  }

  get canIncrement(): boolean {
    return !this.loadingOp;
  }

  get barPercent(): number {
    const q = this.stock.quantity ?? 0;
    const min = this.stock.minimumQuantity ?? 0;
    const max = this.stock.maximumQuantity ?? Math.max(q, min, 1) * 1.25;
    if (max <= 0) return 0;
    return Math.min(100, Math.max(0, (q / max) * 100));
  }

  private get status(): StockStatus {
    if (this.stock.status) return this.stock.status;
    const q = this.stock.quantity ?? 0;
    const min = this.stock.minimumQuantity ?? 0;
    if (q === 0) return 'OUT_OF_STOCK';
    if (q <= min) return 'LOW_STOCK';
    return 'NORMAL';
  }

  onClickDecrement(): void {
    if (this.canDecrement) this.decrement.emit(this.stock.id);
  }

  onClickIncrement(): void {
    if (this.canIncrement) this.increment.emit(this.stock.id);
  }
}
