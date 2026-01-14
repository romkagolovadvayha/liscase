'use client';

import { useQuery } from '@tanstack/react-query';
import apiClient from '@/lib/api/client';

async function fetchSettings(): Promise<Record<string, any>> {
  try {
    const response = await apiClient.get<{ success: boolean; data: Record<string, any> }>('/settings');
    
    if (response.data.success) {
      return response.data.data;
    }
    
    return {};
  } catch (error) {
    console.error('[useSettings] Error fetching settings:', error);
    return {};
  }
}

export function useSettings() {
  return useQuery({
    queryKey: ['settings'],
    queryFn: fetchSettings,
    staleTime: 3 * 60 * 60 * 1000, // 3 часа - настройки меняются редко
    gcTime: 6 * 60 * 60 * 1000, // 6 часов в кеше
    refetchOnWindowFocus: false,
    refetchOnMount: true, // Обновлять при монтировании, если данных нет
  });
}




