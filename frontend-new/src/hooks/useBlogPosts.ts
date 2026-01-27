'use client';

import { useQuery } from '@tanstack/react-query';
import apiClient from '@/lib/api/client';

export interface BlogPost {
  id: number;
  title: string;
  description?: string;
  content?: string;
  image?: string;
  views?: number;
  commentsCount?: number;
  linkName?: string;
  url?: string;
  category?: {
    id: number;
    name: string;
    linkName: string;
  };
  createdAt?: string;
}

interface BlogPostsResponse {
  success: boolean;
  data: {
    posts: BlogPost[];
    pagination?: {
      total: number;
      page: number;
      limit: number;
      totalPages: number;
    };
  };
}

interface UseBlogPostsOptions {
  limit?: number;
  page?: number;
  categoryId?: number;
  search?: string;
  sort?: string;
  order?: 'asc' | 'desc';
  enabled?: boolean;
}

async function fetchBlogPosts(options: UseBlogPostsOptions): Promise<BlogPostsResponse> {
  const params = new URLSearchParams();
  
  if (options.limit) params.append('limit', options.limit.toString());
  if (options.page) params.append('page', options.page.toString());
  if (options.categoryId) params.append('category_id', options.categoryId.toString());
  if (options.search) params.append('search', options.search);
  if (options.sort) params.append('sort', options.sort);
  if (options.order) params.append('order', options.order);

  const response = await apiClient.get<BlogPostsResponse>(`/blog?${params.toString()}`);
  return response.data;
}

export function useBlogPosts(options: UseBlogPostsOptions = {}) {
  const { 
    limit, 
    page, 
    categoryId, 
    search, 
    sort = 'created_at', 
    order = 'desc',
    enabled = true 
  } = options;

  return useQuery({
    queryKey: ['blogPosts', limit, page, categoryId, search, sort, order],
    queryFn: () => fetchBlogPosts({ limit, page, categoryId, search, sort, order }),
    staleTime: 60 * 1000, // 1 минута
    gcTime: 5 * 60 * 1000, // 5 минут в кеше
    refetchOnWindowFocus: false,
    refetchOnMount: false,
    enabled,
  });
}




