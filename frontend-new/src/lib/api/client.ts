import axios, { AxiosInstance, AxiosError, InternalAxiosRequestConfig } from 'axios';

// Базовый URL API
const API_BASE_URL = process.env.API_BASE_URL || '/api/v1';

// Создание экземпляра axios
const apiClient: AxiosInstance = axios.create({
  baseURL: API_BASE_URL,
  timeout: 30000,
  withCredentials: true, // Важно для cookie-based авторизации
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
});

// Interceptor для добавления CSRF токена
apiClient.interceptors.request.use(
  (config: InternalAxiosRequestConfig) => {
    // Получаем CSRF токен из cookie или meta тега
    if (typeof document !== 'undefined') {
      const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
      if (csrfToken && config.headers) {
        config.headers['X-CSRF-Token'] = csrfToken;
      }
    }
    return config;
  },
  (error: AxiosError) => {
    return Promise.reject(error);
  }
);

// Interceptor для обработки ошибок
apiClient.interceptors.response.use(
  (response) => response,
  (error: AxiosError) => {
    // Обработка различных типов ошибок
    if (error.response) {
      // Сервер вернул ошибку
      const { status, data } = error.response;
      
      switch (status) {
        case 401:
          // Не авторизован - редирект на страницу входа
          if (typeof window !== 'undefined') {
            window.location.href = '/auth/login';
          }
          break;
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











