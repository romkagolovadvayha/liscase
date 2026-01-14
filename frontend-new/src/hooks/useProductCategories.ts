'use client';

import { useQuery } from '@tanstack/react-query';
import apiClient from '@/lib/api/client';

export interface Category {
  id: number;
  name: string;
  image?: string;
  tag?: string;
}

interface CategoriesResponse {
  success: boolean;
  data: Category[];
}

async function fetchCategories(showMainBlock?: number): Promise<CategoriesResponse> {
  const params = showMainBlock !== undefined 
    ? `?show_main_block=${showMainBlock}` 
    : '';
  const response = await apiClient.get<CategoriesResponse>(`/products/categories${params}`);
  return response.data;
}

export function useProductCategories(showMainBlock?: number) {
  return useQuery({
    queryKey: ['productCategories', showMainBlock],
    queryFn: () => fetchCategories(showMainBlock),
    staleTime: 10 * 60 * 1000, // 10 минут - категории меняются редко
    gcTime: 30 * 60 * 1000, // 30 минут в кеше
    refetchOnWindowFocus: false,
    refetchOnMount: false,
  });
}




