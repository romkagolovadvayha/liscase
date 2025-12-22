'use client';

import { useQuery } from '@tanstack/react-query';

export interface Server {
  id: number;
  tag: string;
  name: string;
  description: string;
  status: number;
  players: number;
  max: number;
  joined: number;
  queued: number;
  ip: string;
  port: number;
  nextWipe: number;
  wipeType: string;
  monitoring: {
    percentPlayers: number;
    percentJoined: number;
    percentQueued: number;
    percentPlayersAbsolute: number;
    percentJoinedAbsolute: number;
    percentQueuedAbsolute: number;
  };
}

export interface ServersData {
  servers: Server[];
  projectStats: {
    online: number;
  };
}

async function fetchServersData(): Promise<ServersData> {
  const response = await fetch('/api/servers', {
    cache: 'no-store',
  });
  if (!response.ok) {
    throw new Error('Failed to fetch servers data');
  }
  const data = await response.json();
  return data.data || data;
}

export function useServersData(options?: {
  enabled?: boolean;
  initialData?: ServersData;
}) {
  return useQuery({
    queryKey: ['servers'],
    queryFn: fetchServersData,
    staleTime: 30 * 1000, // 30 секунд для серверов (данные часто меняются)
    enabled: options?.enabled !== false,
    initialData: options?.initialData,
    refetchInterval: 60 * 1000, // Обновлять каждую минуту
  });
}

