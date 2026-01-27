'use client';

import React, { createContext, useContext, useEffect, useState, useRef } from 'react';
import { getMe, isAuthenticated, type MeResponse } from '@/lib/api/auth';
import apiClient from '@/lib/api/client';

interface UserData {
  id: number;
  username: string;
  steam_id: string;
  avatar: string;
  roles: string[];
  created_at: string;
  activeVip?: {
    expires_at: string;
    timestamp: number;
  } | null;
  isAdmin?: boolean; // Добавляем isAdmin из API
}

interface UserContextType {
  user: UserData | null;
  isLoading: boolean;
  isGuest: boolean;
  refreshUser: () => Promise<void>;
}

const UserContext = createContext<UserContextType | undefined>(undefined);

export function useUser() {
  const context = useContext(UserContext);
  if (!context) {
    throw new Error('useUser must be used within UserProvider');
  }
  return context;
}

export function UserProvider({ children }: { children: React.ReactNode }) {
  const [user, setUser] = useState<UserData | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [isGuest, setIsGuest] = useState(true);
  const loadingRef = useRef(false);

  const loadUser = async () => {
    // Предотвращаем множественные одновременные запросы
    if (loadingRef.current) {
      return;
    }

    if (!isAuthenticated()) {
      setIsGuest(true);
      setUser(null);
      setIsLoading(false);
      return;
    }

    loadingRef.current = true;
    setIsLoading(true);

    try {
      const userData = await getMe();
      
      if (userData) {
        // Используем isAdmin из API, если он есть, иначе проверяем по roles
        const hasAdminRole = userData.roles && (
          userData.roles.includes('ROLE_ADMIN') || 
          userData.roles.includes('ROLE_MODERATOR') || 
          userData.roles.includes('ROLE_SUPPORT')
        );
        
        // Приоритет: сначала isAdmin из API, потом проверка по roles
        const isAdmin = userData.isAdmin !== undefined 
          ? userData.isAdmin 
          : hasAdminRole || false;

        console.log('[UserProvider] User data loaded:', {
          id: userData.id,
          username: userData.username,
          roles: userData.roles,
          isAdminFromAPI: userData.isAdmin,
          isAdminCalculated: hasAdminRole,
          isAdminFinal: isAdmin,
        });

        setUser({
          ...userData,
          isAdmin: isAdmin,
        });
        setIsGuest(false);
      } else {
        setUser(null);
        setIsGuest(true);
      }
    } catch (error: any) {
      console.error('[UserProvider] Error loading user:', error);
      // Если ошибка 401 - пользователь не авторизован, это нормально
      if (error.response?.status !== 401) {
        console.error('[UserProvider] Unexpected error loading user:', error);
      }
      setUser(null);
      setIsGuest(true);
    } finally {
      setIsLoading(false);
      loadingRef.current = false;
    }
  };

  // Загружаем данные пользователя один раз при монтировании
  useEffect(() => {
    loadUser();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  // Слушаем изменения токена в localStorage
  useEffect(() => {
    const handleStorageChange = (e: StorageEvent) => {
      if (e.key === 'access_token') {
        // Если токен изменился, перезагружаем данные пользователя
        loadUser();
      }
    };

    window.addEventListener('storage', handleStorageChange);
    
    return () => {
      window.removeEventListener('storage', handleStorageChange);
    };
  }, []);

  const refreshUser = async () => {
    await loadUser();
  };

  const value: UserContextType = {
    user,
    isLoading,
    isGuest,
    refreshUser,
  };

  return (
    <UserContext.Provider value={value}>
      {children}
    </UserContext.Provider>
  );
}

