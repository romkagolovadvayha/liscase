/**
 * Типы для системы платежей (Payment/Payout)
 */

export type PaymentStatus = 'wait_confirm' | 'success' | 'failed' | 'cancelled';

export type PaymentProvider = 'tinkoff' | 'yookassa' | 'other';

export interface Deposit {
  id: number;
  user_id: number;
  amount: number;
  payment_id: string;
  payment_provider: PaymentProvider;
  status: PaymentStatus;
  created_at: string;
  updated_at: string;
}

export interface PaymentCallback {
  payment: PaymentProvider;
  data: any;
}

export interface PayoutRequest {
  amount: number;
  method: string;
  details: Record<string, any>;
}

export interface Payout {
  id: number;
  user_id: number;
  amount: number;
  method: string;
  details: string;
  status: PaymentStatus;
  created_at: string;
  updated_at: string;
}







