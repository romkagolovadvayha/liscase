'use client';

import { useEffect, useRef, useState, useCallback } from 'react';

interface WebSocketMessage {
  type: string;
  [key: string]: any;
}

interface UseWebSocketOptions {
  url?: string;
  enabled?: boolean;
  onBalanceUpdate?: (balance: number, balanceStr: string) => void;
  onMessage?: (message: WebSocketMessage) => void;
  onOpen?: () => void; // Callback при открытии соединения
  token?: string;
  steamId?: string;
}

export function useWebSocket({
  url,
  enabled = true,
  onBalanceUpdate,
  onMessage,
  onOpen,
  token,
  steamId,
}: UseWebSocketOptions = {}) {
  const [isConnected, setIsConnected] = useState(false);
  const [balance, setBalance] = useState<number | null>(null);
  const wsRef = useRef<WebSocket | null>(null);
  const reconnectTimeoutRef = useRef<NodeJS.Timeout | null>(null);
  const reconnectAttemptsRef = useRef(0);
  const maxReconnectAttempts = 5;
  const reconnectDelay = 5000;

  const connect = useCallback(() => {
    try {
      if (!enabled || !url) {
        console.log('[WebSocket] Connection disabled or no URL', { enabled, url });
        return;
      }

      // Закрываем предыдущее подключение, если есть
      if (wsRef.current) {
        try {
          wsRef.current.close();
        } catch (error) {
          console.warn('[WebSocket] Error closing previous connection:', error);
        }
        wsRef.current = null;
      }

      console.log('[WebSocket] Attempting to connect to:', url);
      const ws = new WebSocket(url);
      wsRef.current = ws;

      ws.onopen = () => {
        try {
          console.log('[WebSocket] ✅ Connected to:', url);
          setIsConnected(true);
          reconnectAttemptsRef.current = 0;

          // Авторизация, если есть токен (старый формат с steamId)
          if (token && steamId) {
            console.log('[WebSocket] Sending auth request (old format)');
            ws.send(
              JSON.stringify({
                action: 'auth',
                token: token,
                steam_id: steamId,
              })
            );
          } else {
            console.log('[WebSocket] No token or steamId for auto-auth', { hasToken: !!token, hasSteamId: !!steamId });
          }

          // Вызываем callback onOpen
          if (onOpen) {
            onOpen();
          }
        } catch (error) {
          console.error('[WebSocket] Error in onopen:', error);
        }
      };

      ws.onmessage = (event) => {
        try {
          const message: WebSocketMessage = JSON.parse(event.data);
          console.log('[WebSocket] Received message:', message.type);

          // Ping/pong убран - не нужен

          // Обработка обновления баланса
          if (message.type === 'update.balance') {
            console.log('[WebSocket] Balance update received:', message);
            const newBalance = message.balance ?? parseFloat(message.balanceStr?.replace(/\s/g, '') || '0');
            setBalance(newBalance);
            if (onBalanceUpdate) {
              onBalanceUpdate(newBalance, message.balanceStr || newBalance.toString());
            }
          }

          // Передаем все сообщения в обработчик
          if (onMessage) {
            onMessage(message);
          }
        } catch (error) {
          console.error('[WebSocket] Error parsing message:', error);
        }
      };

      ws.onerror = (error) => {
        try {
          console.error('[WebSocket] ❌ WebSocket error:', error);
          console.error('[WebSocket] Error details:', {
            readyState: ws.readyState,
            url: url,
          });
        } catch (err) {
          console.error('[WebSocket] Error in error handler:', err);
        }
      };

      ws.onclose = (event) => {
        try {
          console.log('[WebSocket] 🔌 Disconnected:', { 
            code: event.code, 
            reason: event.reason, 
            wasClean: event.wasClean 
          });
          setIsConnected(false);
          wsRef.current = null;

          // Попытка переподключения только если это не была нормальное закрытие
          if (enabled && !event.wasClean && reconnectAttemptsRef.current < maxReconnectAttempts) {
            reconnectAttemptsRef.current++;
            console.log(`[WebSocket] 🔄 Reconnecting (attempt ${reconnectAttemptsRef.current}/${maxReconnectAttempts})...`);
            reconnectTimeoutRef.current = setTimeout(() => {
              connect();
            }, reconnectDelay);
          } else if (reconnectAttemptsRef.current >= maxReconnectAttempts) {
            console.log('[WebSocket] ⛔ Max reconnection attempts reached');
          } else if (event.wasClean) {
            console.log('[WebSocket] Connection closed cleanly, not reconnecting');
          }
        } catch (error) {
          console.error('[WebSocket] Error in onclose:', error);
        }
      };
    } catch (error) {
      console.error('[WebSocket] ❌ Error creating connection:', error);
      setIsConnected(false);
    }
  }, [enabled, url, token, steamId, onMessage, onBalanceUpdate, onOpen]);

  const disconnect = useCallback(() => {
    try {
      if (reconnectTimeoutRef.current) {
        clearTimeout(reconnectTimeoutRef.current);
        reconnectTimeoutRef.current = null;
      }
      if (wsRef.current) {
        wsRef.current.close();
        wsRef.current = null;
      }
      setIsConnected(false);
      reconnectAttemptsRef.current = 0;
      console.log('[WebSocket] Disconnected manually');
    } catch (error) {
      console.error('[WebSocket] Error in disconnect:', error);
    }
  }, []);

  const sendMessage = useCallback((message: any) => {
    try {
      if (wsRef.current && wsRef.current.readyState === WebSocket.OPEN) {
        const messageStr = typeof message === 'string' ? message : JSON.stringify(message);
        wsRef.current.send(messageStr);
        console.log('[WebSocket] 📤 Message sent:', typeof message === 'string' ? message : JSON.stringify(message).substring(0, 100));
        return true;
      } else {
        console.warn('[WebSocket] ⚠️ Cannot send message: connection not open', { 
          readyState: wsRef.current?.readyState,
          OPEN: WebSocket.OPEN 
        });
        return false;
      }
    } catch (error) {
      console.error('[WebSocket] ❌ Error sending message:', error);
      return false;
    }
  }, []);

  useEffect(() => {
    try {
      console.log('[useWebSocket] Effect triggered:', { enabled, url, hasToken: !!token, hasSteamId: !!steamId });
      
      if (enabled && url) {
        console.log('[useWebSocket] Attempting to connect...');
        connect();
      } else {
        console.log('[useWebSocket] Connection disabled or no URL');
        disconnect();
      }

      return () => {
        try {
          console.log('[useWebSocket] Cleaning up connection');
          disconnect();
        } catch (error) {
          console.error('[useWebSocket] Error in cleanup:', error);
        }
      };
    } catch (error) {
      console.error('[useWebSocket] Error in effect:', error);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [enabled, url]);

  return {
    isConnected,
    balance,
    disconnect,
    connect,
    sendMessage,
  };
}
