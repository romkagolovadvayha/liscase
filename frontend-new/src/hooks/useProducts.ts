'use client';

import { useInfiniteQuery } from '@tanstack/react-query';
import apiClient from '@/lib/api/client';

export interface Product {
  id: number;
  name: string;
  image?: string;
  price: number;
  priceReal?: number;
  count?: number;
  discount?: number;
  category_id: number;
  description?: string;
  drop_type?: number;
  subDrops?: Array<{
    id: number;
    drop_id: number;
    count: number;
    name: string;
    price: number;
    image?: string;
  }>;
  floating_price_percent?: number;
}

export interface ProductsResponse {
  success: boolean;
  data: Product[];
  pagination?: {
    total: number;
    limit: number;
    offset: number;
    hasMore: boolean;
  };
}

interface UseProductsOptions {
  limit?: number;
  categoryId?: number | null;
  search?: string;
  enabled?: boolean;
}

async function fetchProducts(options: UseProductsOptions & { pageParam?: number }): Promise<ProductsResponse> {
  const limit = options.limit || 20;
  const offset = (options.pageParam || 0) * limit;

  const params = new URLSearchParams({
    limit: limit.toString(),
    offset: offset.toString(),
  });

  if (options.categoryId !== null && options.categoryId !== undefined && options.categoryId !== 0) {
    params.append('category_id', options.categoryId.toString());
  }

  if (options.search) {
    params.append('search', options.search);
  }

  const response = await apiClient.get<ProductsResponse>(`/products?${params.toString()}`);
  return response.data;
}

export function useProducts(options: UseProductsOptions = {}) {
  const { limit = 20, categoryId = null, search = '', enabled = true } = options;

  return useInfiniteQuery({
    queryKey: ['products', limit, categoryId, search],
    queryFn: ({ pageParam = 0 }) => fetchProducts({ limit, categoryId, search, pageParam }),
    getNextPageParam: (lastPage, allPages) => {
      const hasMore = lastPage.pagination?.hasMore ?? (lastPage.data.length === limit);
      return hasMore ? allPages.length : undefined;
    },
    initialPageParam: 0,
    staleTime: 30 * 1000, // 30 секунд
    gcTime: 5 * 60 * 1000, // 5 минут в кеше
    refetchOnWindowFocus: false,
    refetchOnMount: false,
    enabled,
  });
}

