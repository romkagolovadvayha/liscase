import axios, { AxiosInstance, AxiosError, InternalAxiosRequestConfig } from 'axios';

// Базовый URL API
const API_BASE_URL = process.env.NEXT_PUBLIC_API_BASE_URL || 'http://api.test.prostoj.store';

// Создание экземпляра axios
const apiClient: AxiosInstance = axios.create({
  baseURL: `${API_BASE_URL}/v1`,
  timeout: 30000,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
});

// Функция для получения токена из localStorage
const getToken = (): string | null => {
  if (typeof window === 'undefined') return null;
  return localStorage.getItem('access_token');
};

const getRefreshToken = (): string | null => {
  if (typeof window === 'undefined') return null;
  return localStorage.getItem('refresh_token');
};

// Функция для сохранения токенов
export const setTokens = (accessToken: string, refreshToken: string): void => {
  if (typeof window === 'undefined') return;
  localStorage.setItem('access_token', accessToken);
  localStorage.setItem('refresh_token', refreshToken);
};

// Функция для очистки токенов
export const clearTokens = (): void => {
  if (typeof window === 'undefined') return;
  localStorage.removeItem('access_token');
  localStorage.removeItem('refresh_token');
};

// Interceptor для добавления JWT токена
apiClient.interceptors.request.use(
  (config: InternalAxiosRequestConfig) => {
    const token = getToken();
    if (token && config.headers) {
      config.headers['Authorization'] = `Bearer ${token}`;
    }
    return config;
  },
  (error: AxiosError) => {
    return Promise.reject(error);
  }
);

// Флаг для предотвращения множественных запросов на обновление токена
let isRefreshing = false;
let failedQueue: Array<{
  resolve: (value?: any) => void;
  reject: (reason?: any) => void;
}> = [];

const processQueue = (error: any, token: string | null = null) => {
  failedQueue.forEach(prom => {
    if (error) {
      prom.reject(error);
    } else {
      prom.resolve(token);
    }
  });
  
  failedQueue = [];
};

// Interceptor для обработки ошибок и обновления токена
apiClient.interceptors.response.use(
  (response) => response,
  async (error: AxiosError) => {
    const originalRequest = error.config as InternalAxiosRequestConfig & { _retry?: boolean };

    // Если ошибка 401 и это не запрос на обновление токена
    if (error.response?.status === 401 && !originalRequest._retry) {
      if (isRefreshing) {
        // Если уже идет обновление, добавляем в очередь
        return new Promise((resolve, reject) => {
          failedQueue.push({ resolve, reject });
        })
          .then(token => {
            if (originalRequest.headers && token) {
              originalRequest.headers['Authorization'] = `Bearer ${token}`;
            }
            return apiClient(originalRequest);
          })
          .catch(err => {
            return Promise.reject(err);
          });
      }

      originalRequest._retry = true;
      isRefreshing = true;

      const refreshToken = getRefreshToken();
      if (!refreshToken) {
        clearTokens();
        processQueue(new Error('No refresh token'), null);
        // Не делаем редирект здесь - пусть компоненты сами обрабатывают отсутствие авторизации
        // if (typeof window !== 'undefined' && window.location.pathname !== '/') {
        //   window.location.href = '/';
        // }
        return Promise.reject(error);
      }

      try {
        // Пытаемся обновить токен
        const response = await axios.post(`${API_BASE_URL}/v1/auth/refresh`, {
          refresh_token: refreshToken,
        });

        const { token: newAccessToken, refresh_token: newRefreshToken } = response.data.data;
        setTokens(newAccessToken, newRefreshToken || refreshToken);

        // Обновляем заголовок для текущего запроса
        if (originalRequest.headers) {
          originalRequest.headers['Authorization'] = `Bearer ${newAccessToken}`;
        }

        processQueue(null, newAccessToken);
        isRefreshing = false;

        return apiClient(originalRequest);
      } catch (refreshError) {
        processQueue(refreshError, null);
        isRefreshing = false;
        clearTokens();
        // Не делаем редирект здесь - пусть компоненты сами обрабатывают ошибку обновления токена
        // if (typeof window !== 'undefined' && window.location.pathname !== '/') {
        //   window.location.href = '/';
        // }
        return Promise.reject(refreshError);
      }
    }

    // Обработка других ошибок
    if (error.response) {
      const { status, data } = error.response;
      
      switch (status) {
        case 403:
          // Доступ запрещен
          console.error('Access denied:', data);
          break;
        case 404:
          // Не найдено
          console.error('Not found:', data);
          break;
        case 500:
          // Ошибка сервера
          console.error('Server error:', data);
          break;
      }
    } else if (error.request) {
      // Запрос был отправлен, но ответ не получен
      console.error('Network error:', error.request);
    } else {
      // Ошибка при настройке запроса
      console.error('Request error:', error.message);
    }
    
    return Promise.reject(error);
  }
);

export default apiClient;
