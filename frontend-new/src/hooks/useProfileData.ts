'use client';

import { useQuery } from '@tanstack/react-query';
import type { PlayerProfileData } from '@/types/profile';

async function fetchProfileData(steamId: string): Promise<PlayerProfileData> {
  // Используем тот же endpoint, что и в page.tsx
  const response = await fetch(`/api/profile/${steamId}`, {
    cache: 'no-store',
  });
  if (!response.ok) {
    throw new Error('Failed to fetch profile data');
  }
  const data = await response.json();
  return data.data || data;
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

