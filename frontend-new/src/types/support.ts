/**
 * Типы для системы поддержки (Support)
 */

export type SupportStatus = 'open' | 'closed';

export interface SupportTicket {
  id: number;
  number: number; // Display number (id + 43242)
  user_id: number;
  server_tag?: string | null;
  status: SupportStatus;
  created_at: string;
  updated_at: string;
  user?: {
    id: number;
    username: string;
    avatar?: string;
    blocked_support?: boolean;
    blocked_support_at?: string | null;
  };
  unread_count?: number;
}

export interface SupportMessage {
  id: number;
  support_id: number;
  user_id: number | null;
  message: string | null;
  created_at: string;
  user?: {
    id: number;
    username: string;
    avatar?: string;
  };
  files?: SupportFile[];
  isRead?: boolean; // Для сообщений от текущего пользователя
}

export interface SupportFile {
  id: number;
  support_message_id: number;
  file: string;
  filename: string;
  mimetype: string;
  created_at: string;
}

export interface SupportSticker {
  id: number;
  code: string;
  name: string;
  type: 'image' | 'video';
  url: string;
  width?: number | null;
  height?: number | null;
}

export interface SupportWebSocketMessage {
  type: 'support.message' | 'support.status' | 'support.typing' | 'support.new_ticket' | 'purchase.completed' | 'support.message.updated' | 'support.message.deleted' | 'support.sync.response';
  ticketId?: number;
  message?: SupportMessage;
  messageId?: number;
  status?: SupportStatus;
  userId?: number;
  typing?: boolean;
  newBalance?: number;
  messageCount?: number; // For sync response
  success?: boolean; // For sync response
}


