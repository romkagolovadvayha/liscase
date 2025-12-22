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
  token?: string;
  steamId?: string;
}

export function useWebSocket({
  url,
  enabled = true,
  onBalanceUpdate,
  onMessage,
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
    if (!enabled || !url) {
      console.log('[WebSocket] Connection disabled or no URL', { enabled, url });
      return;
    }

    console.log('[WebSocket] Attempting to connect to:', url);
    try {
      const ws = new WebSocket(url);
      wsRef.current = ws;

      ws.onopen = () => {
        console.log('[WebSocket] Connected to:', url);
        setIsConnected(true);
        reconnectAttemptsRef.current = 0;

        // Авторизация, если есть токен
        if (token && steamId) {
          console.log('[WebSocket] Sending auth request');
          ws.send(
            JSON.stringify({
              action: 'auth',
              token: token,
              steam_id: steamId,
            })
          );
        } else {
          console.log('[WebSocket] No token or steamId, skipping auth', { hasToken: !!token, hasSteamId: !!steamId });
        }
      };

      ws.onmessage = (event) => {
        try {
          const message: WebSocketMessage = JSON.parse(event.data);
          console.log('[WebSocket] Received message:', message.type);

          // Обработка ping/pong
          if (message.type === 'ping') {
            ws.send(JSON.stringify({ action: 'Pong', ts: message.ts }));
            return;
          }

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
        console.error('[WebSocket] Error:', error);
      };

      ws.onclose = (event) => {
        console.log('[WebSocket] Disconnected:', { code: event.code, reason: event.reason, wasClean: event.wasClean });
        setIsConnected(false);
        wsRef.current = null;

        // Попытка переподключения
        if (enabled && reconnectAttemptsRef.current < maxReconnectAttempts) {
          reconnectAttemptsRef.current++;
          console.log(`[WebSocket] Reconnecting (attempt ${reconnectAttemptsRef.current}/${maxReconnectAttempts})...`);
          reconnectTimeoutRef.current = setTimeout(() => {
            connect();
          }, reconnectDelay);
        } else if (reconnectAttemptsRef.current >= maxReconnectAttempts) {
          console.log('[WebSocket] Max reconnection attempts reached');
        }
      };
    } catch (error) {
      console.error('[WebSocket] Error creating connection:', error);
    }
  }, [enabled, url, token, steamId]);

  const disconnect = useCallback(() => {
    if (reconnectTimeoutRef.current) {
      clearTimeout(reconnectTimeoutRef.current);
      reconnectTimeoutRef.current = null;
    }
    if (wsRef.current) {
      wsRef.current.close();
      wsRef.current = null;
    }
    setIsConnected(false);
  }, []);

  useEffect(() => {
    console.log('[useWebSocket] Effect triggered:', { enabled, url, hasToken: !!token, hasSteamId: !!steamId });
    
    if (enabled && url) {
      console.log('[useWebSocket] Attempting to connect...');
      connect();
    } else {
      console.log('[useWebSocket] Connection disabled or no URL');
    }

    return () => {
      console.log('[useWebSocket] Cleaning up connection');
      disconnect();
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [enabled, url]);

  return {
    isConnected,
    balance,
    disconnect,
    connect,
  };
}

