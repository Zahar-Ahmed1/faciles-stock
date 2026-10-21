export interface Client {
  id: number;
  code: string;
  name: string;
  email: string;
  phone?: string;
  address?: string;
  active?: boolean;
  createdAt?: string;
  updatedAt?: string;
}

export interface Site {
  id: number;
  clientId: number;
  clientName?: string;
  name: string;
  code?: string;
  address?: string;
  city?: string;
  postalCode?: string;
  country?: string;
  active?: boolean;
  createdAt?: string;
  updatedAt?: string;
}

export interface SitePayload {
  clientId: number;
  name: string;
  code?: string;
  address?: string;
  city?: string;
  active?: boolean;
}

export interface Category {
  id: number;
  name: string;
  description?: string;
  active?: boolean;
  createdAt?: string;
  updatedAt?: string;
}

export interface Product {
  id: number;
  reference: string;
  name: string;
  imageUrl?: string;
  unit: string;
  categoryId: number;
  active?: boolean;
  createdAt?: string;
  updatedAt?: string;
}
