'use client';

import { useQuery } from '@tanstack/react-query';
import type { PlayerProfileData } from '@/types/profile';

async function fetchProfileData(steamId: string): Promise<PlayerProfileData> {
  // Используем API endpoint для получения данных профиля
  const API_BASE_URL = process.env.NEXT_PUBLIC_API_BASE_URL || 'http://api.test.prostoj.store';
  const response = await fetch(`${API_BASE_URL}/v1/stats/player-new?steam_id=${steamId}`, {
    cache: 'no-store',
  });
  if (!response.ok) {
    throw new Error('Failed to fetch profile data');
  }
  const result = await response.json();
  return result.data || result;
}

export function useProfileData(
  steamId: string,
  options?: {
    enabled?: boolean;
    initialData?: PlayerProfileData;
  }
) {
  return useQuery({
    queryKey: ['profile', steamId],
    queryFn: () => fetchProfileData(steamId),
    staleTime: 5 * 60 * 1000, // 5 минут для профиля
    enabled: options?.enabled !== false && !!steamId,
    initialData: options?.initialData,
  });
}

