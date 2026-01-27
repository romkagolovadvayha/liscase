'use client';

import React, { createContext, useContext, useEffect, useState, useRef } from 'react';
import { useUser } from '@/providers/UserProvider';
import apiClient from '@/lib/api/client';

interface HomepageUserData {
  username: string;
  userStats?: Record<string, number>;
  awards?: Array<{ id: number; name: string; image: string; completed: boolean }>;
  awardsStats?: { completed: number; total: number };
  userStatsLink?: string | null;
  serverActiveTag?: string | null;
}

interface HomepageDataContextType {
  userData: HomepageUserData | null;
  isLoading: boolean;
  refreshHomepageData: () => Promise<void>;
}

const HomepageDataContext = createContext<HomepageDataContextType | undefined>(undefined);

export function useHomepageData() {
  const context = useContext(HomepageDataContext);
  if (!context) {
    throw new Error('useHomepageData must be used within HomepageDataProvider');
  }
  return context;
}

export function HomepageDataProvider({ children }: { children: React.ReactNode }) {
  const { user, isGuest } = useUser();
  const [userData, setUserData] = useState<HomepageUserData | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const loadingRef = useRef(false);

  const loadHomepageData = async () => {
    // Предотвращаем множественные одновременные запросы
    if (loadingRef.current) {
      return;
    }

    // Если пользователь не авторизован, не загружаем данные
    if (isGuest || !user) {
      setUserData(null);
      setIsLoading(false);
      return;
    }

    loadingRef.current = true;
    setIsLoading(true);

    try {
      const response = await apiClient.get('/user/homepage-data');
      if (response.data.success && response.data.data) {
        const data = response.data.data;
        setUserData({
          username: data.username,
          userStats: data.userStats || {},
          awards: data.awards || [],
          awardsStats: data.awardsStats || { completed: 0, total: 0 },
          userStatsLink: data.userStatsLink || null,
          serverActiveTag: data.serverActiveTag || null,
        });
      } else {
        setUserData(null);
      }
    } catch (error: any) {
      console.error('[HomepageDataProvider] Error loading homepage data:', error);
      // Если ошибка 401 - пользователь не авторизован, это нормально
      if (error.response?.status !== 401) {
        console.error('[HomepageDataProvider] Unexpected error loading homepage data:', error);
      }
      setUserData(null);
    } finally {
      setIsLoading(false);
      loadingRef.current = false;
    }
  };

  // Загружаем данные при изменении состояния авторизации пользователя
  useEffect(() => {
    loadHomepageData();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [user, isGuest]);

  const refreshHomepageData = async () => {
    await loadHomepageData();
  };

  const value: HomepageDataContextType = {
    userData,
    isLoading,
    refreshHomepageData,
  };

  return (
    <HomepageDataContext.Provider value={value}>
      {children}
    </HomepageDataContext.Provider>
  );
}

