export type StockStatus = 'NORMAL' | 'LOW_STOCK' | 'OUT_OF_STOCK';

export interface ClientStock {
  id: number;
  siteId: number;
  productId: number;
  productName?: string;
  productImageUrl?: string;
  productUnit?: string;
  quantity: number;
  minimumQuantity: number;
  maximumQuantity?: number;
  status?: StockStatus;
  updatedAt?: string;
  createdAt?: string;
}

export type MovementType = 'ENTRY' | 'EXIT' | 'ADJUSTMENT';

export interface StockMovement {
  id: number;
  siteId: number;
  productId: number;
  type: MovementType;
  quantity: number;
  stockBefore: number;
  stockAfter: number;
  userId: number;
  reason?: string;
  createdAt: string;
  siteName?: string;
  productName?: string;
  userFullName?: string;
}

export type AlertType = 'LOW_STOCK' | 'OUT_OF_STOCK';
export type AlertStatus = 'UNREAD' | 'READ' | 'RESOLVED';

export interface Alert {
  id: number;
  stockId: number;
  siteId: number;
  productId: number;
  type: AlertType;
  message: string;
  status: AlertStatus;
  createdAt: string;
  resolvedAt?: string;
  productName?: string;
  productImageUrl?: string;
  siteName?: string;
  quantityAtAlert?: number;
  threshold?: number;
}

export type ProposalStatus = 'PENDING' | 'CONFIRMED' | 'REJECTED';

export interface DeliveryProposal {
  id: number;
  alertId: number;
  stockId: number;
  siteId: number;
  siteName?: string;
  productId: number;
  productName?: string;
  quantity: number;
  status: ProposalStatus;
  proposedBy: number;
  proposedByName?: string;
  confirmedBy?: number | null;
  managerConfirmedBy?: number | null;
  managerConfirmedByName?: string | null;
  responsableConfirmedBy?: number | null;
  responsableConfirmedByName?: string | null;
  createdAt?: string;
  decidedAt?: string;
}

export interface ProposeDeliveryResponse {
  proposal: DeliveryProposal;
  stockUpdated: boolean;
}

export interface ConfirmProposalResponse {
  proposal: DeliveryProposal;
  alert?: Alert | null;
  followUpAlert?: Alert | null;
  delivery?: StockOperationResponse | null;
}

export interface DeliveryResponse {
  alert: Alert;
  followUpAlert?: Alert | null;
  delivery: StockOperationResponse;
}

export interface StockOperationResponse {
  stockId: number;
  productId: number;
  productName: string;
  oldQuantity: number;
  quantity: number;
  minimumQuantity: number;
  status: StockStatus;
  movementId: number;
  alertCreated: boolean;
}
