'use client';

import { useState, useEffect } from 'react';
import { usePathname } from 'next/navigation';

/**
 * Хук для отслеживания состояния загрузки навигации
 * Отслеживает переходы между страницами и возвращает множество загружаемых маршрутов
 */
export function useNavigationLoading() {
  const [loadingRoutes, setLoadingRoutes] = useState<Set<string>>(new Set());
  const pathname = usePathname();

  useEffect(() => {
    // Сбрасываем состояние загрузки при изменении pathname
    setLoadingRoutes(new Set());
  }, [pathname]);

  const setLoading = (url: string, isLoading: boolean) => {
    setLoadingRoutes((prev) => {
      const next = new Set(prev);
      if (isLoading) {
        next.add(url);
      } else {
        next.delete(url);
      }
      return next;
    });
  };

  const isLoading = (url: string): boolean => {
    return loadingRoutes.has(url);
  };

  return {
    loadingRoutes,
    setLoading,
    isLoading,
  };
}

