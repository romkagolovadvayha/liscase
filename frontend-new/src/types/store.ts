/**
 * Типы для системы выдачи предметов на сервер (Store)
 */

export type StoreItemStatus = 'active' | 'delivered' | 'sell' | 'returned';

export interface StoreItem {
  id: number;
  user_id: number;
  drop_id: number;
  box_id?: number | null;
  sets_id?: number | null;
  parent_drop_id?: number | null;
  status: StoreItemStatus;
  count: number;
  created_at: string;
  drop?: {
    id: number;
    name: string;
    image?: string;
    category_id: number;
    price: number;
  };
  category?: {
    id: number;
    name: string;
  };
}

export interface StoreDeliveryRequest {
  itemId: number;
  serverId: number;
}

export interface StoreDeliveryResponse {
  success: boolean;
  message?: string;
  itemId?: number;
  status?: StoreItemStatus;
}

export interface StoreReturnRequest {
  itemId: number;
}

export interface StoreWebSocketMessage {
  type: 'store.deliver' | 'store.deliver.status' | 'store.return' | 'store.inventory.update' | 'store.item.status';
  itemId?: number;
  serverId?: number;
  userId?: number;
  status?: StoreItemStatus | 'processing' | 'success' | 'error';
  message?: string;
  items?: StoreItem[];
}







