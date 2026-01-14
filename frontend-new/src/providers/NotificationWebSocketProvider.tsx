'use client';

import React, { createContext, useContext, useEffect, useRef, useState, useCallback } from 'react';
import { useWebSocket } from '@/hooks/useWebSocket';
import { isAuthenticated } from '@/lib/api/auth';

interface NotificationWebSocketContextType {
  isConnected: boolean;
  sendMessage: (message: any) => boolean;
  registerTicketHandlers: (ticketId: number, handlers: TicketHandlers) => () => void;
  sendTyping: (ticketId: number, typing: boolean) => void;
  syncTicket: (ticketId: number) => boolean;
}

interface TicketHandlers {
  onMessage?: (messageId: number, userId: number) => void;
  onStatus?: (status: string) => void;
  onTyping?: (userId: number, typing: boolean) => void;
}

const NotificationWebSocketContext = createContext<NotificationWebSocketContextType | undefined>(undefined);

// Функция для получения токена из localStorage
const getToken = (): string | null => {
  try {
    if (typeof window === 'undefined') return null;
    return localStorage.getItem('access_token');
  } catch (error) {
    console.error('[NotificationWebSocket] Error getting token:', error);
    return null;
  }
};

// Функция для получения user_id из JWT токена
const getUserIdFromToken = (): number | null => {
  try {
    const token = getToken();
    if (!token) return null;
    
    // JWT токен состоит из трех частей, разделенных точками: header.payload.signature
    const parts = token.split('.');
    if (parts.length !== 3) return null;
    
    // Декодируем payload (вторая часть)
    const payload = JSON.parse(atob(parts[1]));
    return payload.user_id || null;
  } catch (error) {
    console.error('[NotificationWebSocket] Error decoding token:', error);
    return null;
  }
};

export function useNotificationWebSocket() {
  const context = useContext(NotificationWebSocketContext);
  if (!context) {
    throw new Error('useNotificationWebSocket must be used within NotificationWebSocketProvider');
  }
  return context;
}

export function NotificationWebSocketProvider({ children }: { children: React.ReactNode }) {
  const [isConnected, setIsConnected] = useState(false);
  const authenticatedRef = useRef<boolean>(false);
  const ticketHandlersRef = useRef<Map<number, TicketHandlers>>(new Map());
  const sendMessageRef = useRef<((message: any) => boolean) | null>(null);
  const authTimeoutRef = useRef<NodeJS.Timeout | null>(null);
  const disconnectRef = useRef<(() => void) | null>(null);

  // Получаем WebSocket URL
  const wsUrl = process.env.NEXT_PUBLIC_WS_URL || (typeof window !== 'undefined' ? `ws://${window.location.hostname}:4888` : undefined);
  
  // Получаем токен для WebSocket
  const [wsToken] = useState<string | null>(() => {
    try {
      return getToken();
    } catch (error) {
      console.error('[NotificationWebSocket] Error initializing token:', error);
      return null;
    }
  });

  // Проверяем авторизацию
  const [authChecked, setAuthChecked] = useState(false);
  useEffect(() => {
    try {
      const authenticated = typeof window !== 'undefined' && isAuthenticated();
      setAuthChecked(true);
      console.log('[NotificationWebSocket] Auth check:', { authenticated, hasToken: !!wsToken });
    } catch (error) {
      console.error('[NotificationWebSocket] Error checking auth:', error);
      setAuthChecked(true);
    }
  }, []);

  const enabled = authChecked && typeof window !== 'undefined' && isAuthenticated() && !!wsToken;

  console.log('[NotificationWebSocket] Provider state:', { 
    enabled, 
    wsUrl, 
    hasToken: !!wsToken, 
    authChecked 
  });

  const { isConnected: wsConnected, sendMessage: wsSendMessage, disconnect: wsDisconnect } = useWebSocket({
    url: enabled ? wsUrl : undefined,
    enabled: enabled,
    token: wsToken || undefined,
    steamId: undefined, // Новый сервер не требует steamId
    onOpen: () => {
      // Авторизация сразу при подключении
      try {
        if (wsToken && wsSendMessage) {
          console.log('[NotificationWebSocket] 🔐 Sending auth immediately on connection');
          const authResult = wsSendMessage({
            action: 'auth',
            token: wsToken,
          });
          
          if (!authResult) {
            console.error('[NotificationWebSocket] ❌ Failed to send auth message');
            if (wsDisconnect) {
              wsDisconnect();
            }
            return;
          }

          // Устанавливаем таймаут авторизации (5 секунд)
          authTimeoutRef.current = setTimeout(() => {
            if (!authenticatedRef.current) {
              console.error('[NotificationWebSocket] ❌ Authentication timeout - closing connection');
              authenticatedRef.current = false;
              setIsConnected(false);
              if (wsDisconnect) {
                wsDisconnect();
              }
            }
          }, 5000);
        } else {
          console.error('[NotificationWebSocket] ❌ No token - closing connection');
          if (wsDisconnect) {
            wsDisconnect();
          }
        }
      } catch (error) {
        console.error('[NotificationWebSocket] Error in onOpen:', error);
        if (wsDisconnect) {
          wsDisconnect();
        }
      }
    },
    onMessage: (wsMessage: any) => {
      try {
        console.log('[NotificationWebSocket] Received message:', wsMessage);
        
        // Обработка авторизации
        if (wsMessage.success !== undefined && wsMessage.type === undefined) {
          // Очищаем таймаут авторизации
          if (authTimeoutRef.current) {
            clearTimeout(authTimeoutRef.current);
            authTimeoutRef.current = null;
          }

          if (wsMessage.success) {
            authenticatedRef.current = true;
            setIsConnected(true);
            console.log('[NotificationWebSocket] ✅ Authenticated successfully');
          } else {
            console.error('[NotificationWebSocket] ❌ Authentication failed:', wsMessage.message);
            authenticatedRef.current = false;
            setIsConnected(false);
            // Закрываем соединение при неудачной авторизации
            if (wsDisconnect) {
              console.log('[NotificationWebSocket] Closing connection due to auth failure');
              wsDisconnect();
            }
          }
          return;
        }

        // Обработка сообщений поддержки (только если авторизованы)
        if (!authenticatedRef.current) {
          console.warn('[NotificationWebSocket] Received message but not authenticated, ignoring');
          return;
        }

        if (wsMessage.type === 'support.message') {
          const ticketId = wsMessage.ticketId;
          const handlers = ticketHandlersRef.current.get(ticketId);
          if (handlers?.onMessage) {
            handlers.onMessage(wsMessage.messageId, wsMessage.userId);
          }
          
          // Воспроизводим звук нового сообщения только если:
          // 1. Сообщение не от текущего пользователя
          // 2. Тикет не открыт (нет обработчиков для этого тикета)
          const currentUserId = getUserIdFromToken();
          const isOwnMessage = currentUserId !== null && wsMessage.userId === currentUserId;
          const isTicketOpen = handlers !== undefined; // Если есть обработчики, значит тикет открыт
          
          if (!isOwnMessage && !isTicketOpen) {
            try {
              const audio = new Audio('/sounds/notification.mp3');
              audio.volume = 0.5; // Устанавливаем громкость (0.0 - 1.0)
              audio.play().catch((error) => {
                console.warn('[NotificationWebSocket] Failed to play notification sound:', error);
              });
            } catch (error) {
              console.warn('[NotificationWebSocket] Error creating audio for notification:', error);
            }
          }
        } else if (wsMessage.type === 'support.status') {
          const ticketId = wsMessage.ticketId;
          const handlers = ticketHandlersRef.current.get(ticketId);
          if (handlers?.onStatus && wsMessage.status) {
            handlers.onStatus(wsMessage.status);
          }
          // Отправляем событие для обновления списка тикетов
          if (typeof window !== 'undefined') {
            window.dispatchEvent(new CustomEvent('support-ticket-status-updated', {
              detail: { ticketId, status: wsMessage.status }
            }));
          }
        } else if (wsMessage.type === 'support.new_ticket') {
          // Отправляем событие для обновления списка тикетов при создании нового тикета
          if (typeof window !== 'undefined') {
            window.dispatchEvent(new CustomEvent('support-ticket-created', {
              detail: { ticketId: wsMessage.ticketId, userId: wsMessage.userId }
            }));
          }
        } else if (wsMessage.type === 'support.typing') {
          const ticketId = wsMessage.ticketId;
          const handlers = ticketHandlersRef.current.get(ticketId);
          if (handlers?.onTyping && wsMessage.userId !== undefined && wsMessage.typing !== undefined) {
            handlers.onTyping(wsMessage.userId, wsMessage.typing);
          }
        } else if (wsMessage.type === 'purchase.completed') {
          // Уведомление о покупке - диспатчим событие для обновления баланса
          if (typeof window !== 'undefined') {
            window.dispatchEvent(new CustomEvent('purchase-completed', { 
              detail: { newBalance: wsMessage.newBalance } 
            }));
          }
        } else if (wsMessage.type === 'support.message.updated') {
          // Уведомление об обновлении сообщения
          if (typeof window !== 'undefined' && wsMessage.ticketId !== undefined && wsMessage.messageId !== undefined) {
            window.dispatchEvent(new CustomEvent('support-message-updated', {
              detail: { ticketId: wsMessage.ticketId, messageId: wsMessage.messageId }
            }));
          }
        } else if (wsMessage.type === 'support.message.deleted') {
          // Уведомление об удалении сообщения
          if (typeof window !== 'undefined' && wsMessage.ticketId !== undefined && wsMessage.messageId !== undefined) {
            console.log('[NotificationWebSocket] Dispatching support-message-deleted event:', {
              ticketId: wsMessage.ticketId,
              messageId: wsMessage.messageId
            });
            window.dispatchEvent(new CustomEvent('support-message-deleted', {
              detail: { ticketId: wsMessage.ticketId, messageId: wsMessage.messageId }
            }));
          }
        } else if (wsMessage.type === 'support.sync.response') {
          // Ответ на запрос синхронизации тикета
          if (typeof window !== 'undefined' && wsMessage.ticketId !== undefined && wsMessage.messageCount !== undefined) {
            window.dispatchEvent(new CustomEvent('support-ticket-sync', {
              detail: { ticketId: wsMessage.ticketId, messageCount: wsMessage.messageCount, success: wsMessage.success }
            }));
          }
        }
      } catch (error) {
        console.error('[NotificationWebSocket] Error handling message:', error);
      }
    },
  });

  // Сохраняем ссылки
  useEffect(() => {
    try {
      sendMessageRef.current = wsSendMessage;
      disconnectRef.current = wsDisconnect;
    } catch (error) {
      console.error('[NotificationWebSocket] Error setting refs:', error);
    }
  }, [wsSendMessage, wsDisconnect]);

  // Обновляем isConnected
  useEffect(() => {
    try {
      const newConnected = wsConnected && authenticatedRef.current;
      setIsConnected(newConnected);
      console.log('[NotificationWebSocket] Connection state:', { wsConnected, authenticated: authenticatedRef.current, isConnected: newConnected });
    } catch (error) {
      console.error('[NotificationWebSocket] Error updating connection state:', error);
    }
  }, [wsConnected]);

  // Очистка таймаута при размонтировании
  useEffect(() => {
    return () => {
      if (authTimeoutRef.current) {
        clearTimeout(authTimeoutRef.current);
      }
    };
  }, []);

  // Функция для отправки сообщений
  const sendMessage = useCallback((message: any): boolean => {
    try {
      if (sendMessageRef.current && authenticatedRef.current) {
        const result = sendMessageRef.current(message);
        console.log('[NotificationWebSocket] Message sent:', { message, result });
        return result;
      } else {
        console.warn('[NotificationWebSocket] Cannot send message:', { 
          hasSendMessage: !!sendMessageRef.current, 
          authenticated: authenticatedRef.current 
        });
        return false;
      }
    } catch (error) {
      console.error('[NotificationWebSocket] Error in sendMessage:', error);
      return false;
    }
  }, []);

  // Регистрация обработчиков для тикета (без подписки на сервер, сообщения приходят автоматически)
  const registerTicketHandlers = useCallback((ticketId: number, handlers: TicketHandlers) => {
    try {
      console.log('[NotificationWebSocket] Registering handlers for ticket:', ticketId);
      // Сохраняем обработчики
      ticketHandlersRef.current.set(ticketId, handlers);

      // Возвращаем функцию отписки (удаление обработчиков)
      return () => {
        try {
          ticketHandlersRef.current.delete(ticketId);
          console.log('[NotificationWebSocket] Unregistered handlers for ticket:', ticketId);
        } catch (error) {
          console.error('[NotificationWebSocket] Error unregistering handlers:', error);
        }
      };
    } catch (error) {
      console.error('[NotificationWebSocket] Error in registerTicketHandlers:', error);
      return () => {}; // Пустая функция отписки в случае ошибки
    }
  }, []);

  // Отправка typing индикатора
  const sendTyping = useCallback((ticketId: number, typing: boolean) => {
    try {
      if (authenticatedRef.current && sendMessageRef.current) {
        sendMessageRef.current({
          action: 'typing',
          ticketId: ticketId,
          typing: typing,
        });
      }
    } catch (error) {
      console.error('[NotificationWebSocket] Error sending typing:', error);
    }
  }, []);

  // Отправка запроса синхронизации тикета
  const syncTicket = useCallback((ticketId: number) => {
    try {
      if (authenticatedRef.current && sendMessageRef.current) {
        return sendMessageRef.current({
          action: 'syncTicket',
          ticketId: ticketId,
        });
      }
      return false;
    } catch (error) {
      console.error('[NotificationWebSocket] Error syncing ticket:', error);
      return false;
    }
  }, []);

  const value: NotificationWebSocketContextType = {
    isConnected,
    sendMessage,
    registerTicketHandlers,
    sendTyping,
    syncTicket,
  };

  return (
    <NotificationWebSocketContext.Provider value={value}>
      {children}
    </NotificationWebSocketContext.Provider>
  );
}
