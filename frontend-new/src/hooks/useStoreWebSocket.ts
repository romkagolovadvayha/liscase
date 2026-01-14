'use client';

import { useEffect, useState } from 'react';
import { useWebSocket } from './useWebSocket';
import type { StoreItem } from '@/types/store';
import apiClient from '@/lib/api/client';

interface UseStoreWebSocketOptions {
  enabled?: boolean;
  onInventoryUpdate?: (items: StoreItem[]) => void;
  onDeliveryStatus?: (itemId: number, status: string, message?: string) => void;
}

export function useStoreWebSocket({
  enabled = true,
  onInventoryUpdate,
  onDeliveryStatus,
}: UseStoreWebSocketOptions = {}) {
  const [wsToken, setWsToken] = useState<string | null>(null);
  const [steamId, setSteamId] = useState<string | null>(null);

  useEffect(() => {
    // TODO: ws-token endpoint пока не реализован в новом API
    // if (enabled) {
    //   apiClient.get('/auth/ws-token')
    //     .then((res) => {
    //       const data = res.data;
    //       if (data.success && data.data?.token && data.data?.steam_id) {
    //         setWsToken(data.data.token);
    //         setSteamId(data.data.steam_id);
    //       }
    //     })
    //     .catch((error) => {
    //       console.error('Error getting WS token:', error);
    //     });
    // }
    setWsToken(null);
    setSteamId(null);
  }, [enabled]);

  const wsUrl = process.env.NEXT_PUBLIC_WS_URL || 'ws://localhost:4888';
  const { isConnected } = useWebSocket({
    url: enabled && wsToken ? wsUrl : undefined,
    enabled: enabled && !!wsToken,
    token: wsToken || undefined,
    steamId: steamId || undefined,
    onMessage: (wsMessage: any) => {
      if (wsMessage.type === 'store.inventory.update' && wsMessage.items) {
        if (onInventoryUpdate) {
          onInventoryUpdate(wsMessage.items);
        }
      } else if (wsMessage.type === 'store.deliver.status') {
        if (onDeliveryStatus && wsMessage.itemId) {
          onDeliveryStatus(
            wsMessage.itemId,
            wsMessage.status || 'processing',
            wsMessage.message
          );
        }
      } else if (wsMessage.type === 'store.return' && wsMessage.itemId) {
        // Обновляем инвентарь после возврата
        // TODO: Запросить обновленный список через API
      }
    },
  });

  return {
    isConnected,
  };
}

