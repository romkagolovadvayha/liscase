import apiClient from './client';
import { setTokens, clearTokens } from './client';

export interface LoginResponse {
  success: boolean;
  data: {
    token: string;
    refresh_token: string;
    expires_in: number;
    user: {
      id: number;
      username: string;
      steam_id: string;
      avatar: string;
      roles: string[];
    };
  };
}

export interface MeResponse {
  success: boolean;
  data: {
    id: number;
    username: string;
    steam_id: string;
    avatar: string;
    roles: string[];
    created_at: string;
    isAdmin?: boolean; // Добавляем isAdmin из API
    activeVip?: {
      expires_at: string;
      timestamp: number;
    } | null;
  };
}

/**
 * Авторизация через Steam OAuth
 */
export const startSteamAuth = (): void => {
  const apiBaseUrl = process.env.NEXT_PUBLIC_API_BASE_URL || 'http://api.test.prostoj.store';
  // Передаем текущий origin фронтенда для редиректа после авторизации
  const redirectUri = typeof window !== 'undefined' ? window.location.origin : '';
  const oauthUrl = new URL(`${apiBaseUrl}/v1/auth/oauth`);
  if (redirectUri) {
    oauthUrl.searchParams.set('redirect_uri', redirectUri);
  }
  window.location.href = oauthUrl.toString();
};

/**
 * Получение информации о текущем пользователе
 */
export const getMe = async (): Promise<MeResponse['data']> => {
  const response = await apiClient.get<MeResponse>('/auth/me');
  return response.data.data;
};

/**
 * Выход из системы
 */
export const logout = async (): Promise<void> => {
  try {
    await apiClient.get('/auth/logout');
  } catch (error) {
    console.error('Logout error:', error);
  } finally {
    clearTokens();
    if (typeof window !== 'undefined') {
      window.location.href = '/';
    }
  }
};

/**
 * Проверка, авторизован ли пользователь
 */
export const isAuthenticated = (): boolean => {
  if (typeof window === 'undefined') return false;
  return !!localStorage.getItem('access_token');
};
