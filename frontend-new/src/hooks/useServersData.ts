'use client';

import { useQuery } from '@tanstack/react-query';
import apiClient from '@/lib/api/client';

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
  const response = await apiClient.get('/servers');
  const data = response.data;
  const result = data.data || data;
  
  // Маппинг project_stats в projectStats
  if (result.project_stats) {
    result.projectStats = result.project_stats;
    delete result.project_stats;
  }
  
  return result;
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

