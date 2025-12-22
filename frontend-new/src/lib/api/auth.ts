import apiClient from './client';
import { User } from '@/types/user';

export interface AuthResponse {
  user: User | null;
  authenticated: boolean;
}

export interface LoginResponse {
  success: boolean;
  user?: User;
  error?: string;
}

/**
 * Проверка текущей сессии пользователя
 */
export async function checkAuth(): Promise<AuthResponse> {
  try {
    const response = await apiClient.get<AuthResponse>('/auth/check');
    return response.data;
  } catch (error) {
    return {
      user: null,
      authenticated: false,
    };
  }
}

/**
 * Выход пользователя
 */
export async function logout(): Promise<void> {
  await apiClient.post('/auth/logout');
}

/**
 * Получение текущего пользователя
 */
export async function getCurrentUser(): Promise<User | null> {
  try {
    const response = await apiClient.get<User>('/auth/me');
    return response.data;
  } catch (error) {
    return null;
  }
}











