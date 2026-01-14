/**
 * Серверная функция для получения настроек
 * Используется в Server Components Next.js
 */

const API_BASE_URL = process.env.NEXT_PUBLIC_API_BASE_URL || 'http://api.test.prostoj.store';

interface SettingsCache {
  data: Record<string, any>;
  timestamp: number;
}

// Кеш на 3 часа (на уровне сервера)
const CACHE_DURATION = 3 * 60 * 60 * 1000; // 3 часа в миллисекундах
let settingsCache: SettingsCache | null = null;

/**
 * Получить все настройки сайта на сервере
 * Использует кеширование на 3 часа
 */
export async function getSettingsServer(update: boolean = false): Promise<Record<string, any>> {
  // Проверяем кеш
  if (!update && settingsCache && Date.now() - settingsCache.timestamp < CACHE_DURATION) {
    return settingsCache.data;
  }

  try {
    const response = await fetch(`${API_BASE_URL}/v1/settings`, {
      method: 'GET',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
      // Не кешируем на уровне fetch, используем свой кеш
      cache: 'no-store',
    });

    if (!response.ok) {
      console.error('[getSettingsServer] Failed to fetch settings:', response.statusText);
      return {};
    }

    const data = await response.json();
    
    if (data.success) {
      // Сохраняем в кеш
      settingsCache = {
        data: data.data,
        timestamp: Date.now(),
      };
      return data.data;
    }
    
    return {};
  } catch (error) {
    console.error('[getSettingsServer] Error fetching settings:', error);
    return {};
  }
}

