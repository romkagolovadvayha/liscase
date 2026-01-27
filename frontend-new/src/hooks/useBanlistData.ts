'use client';

import { useQuery } from '@tanstack/react-query';

export interface BanlistFilters {
  steam_id?: string;
  reason?: string;
  server_id?: string;
  page?: number;
  sort?: 'username' | 'server' | 'first_seen' | 'banned_at' | 'reason';
  order?: 'asc' | 'desc';
}

export interface Ban {
  id: number;
  username: string;
  steam_id: string;
  avatar: string;
  reason: string;
  banned_at: string;
  unbanned_at: string | null;
  server_id: number | null;
  server_name: string;
  server_tag?: string;
  first_seen: string | null;
}

export interface BanlistResponse {
  success: boolean;
  data: Ban[];
  pagination: {
    page: number;
    totalPages: number;
    total: number;
  };
}

import apiClient from '@/lib/api/client';

async function fetchBanlistData(filters: BanlistFilters): Promise<BanlistResponse> {
  const params = new URLSearchParams();
  if (filters.page && filters.page > 1) params.set('page', filters.page.toString());
  if (filters.steam_id) params.set('steam_id', filters.steam_id);
  if (filters.reason) params.set('reason', filters.reason);
  if (filters.server_id) params.set('server_id', filters.server_id);
  if (filters.sort) params.set('sort', filters.sort);
  if (filters.order) params.set('order', filters.order);

  const response = await apiClient.get<BanlistResponse>(`/banlist?${params.toString()}`);
  return response.data;
}

export function useBanlistData(
  filters: BanlistFilters = {},
  options?: {
    enabled?: boolean;
    initialData?: BanlistResponse;
  }
) {
  return useQuery({
    queryKey: ['banlist', filters],
    queryFn: () => fetchBanlistData(filters),
    staleTime: 2 * 60 * 1000, // 2 минуты для банлиста
    enabled: options?.enabled !== false,
    initialData: options?.initialData,
  });
}

