import apiClient from '@/lib/api/client';

interface SettingsCache {
  data: Record<string, any>;
  timestamp: number;
}

// Кеш на 3 часа
const CACHE_DURATION = 3 * 60 * 60 * 1000; // 3 часа в миллисекундах
let settingsCache: SettingsCache | null = null;

/**
 * Получить значение настройки по ключу
 * Ключ формируется как category_code (например: "design_logo")
 */
export async function getSetting(key: string): Promise<string | boolean | number> {
  const settings = await getSettings();
  return settings[key] || '';
}

/**
 * Получить все настройки сайта
 * Использует кеширование на 3 часа
 */
export async function getSettings(update: boolean = false): Promise<Record<string, any>> {
  // Проверяем кеш
  if (!update && settingsCache && Date.now() - settingsCache.timestamp < CACHE_DURATION) {
    return settingsCache.data;
  }

  try {
    const response = await apiClient.get<{ success: boolean; data: Record<string, any> }>('/settings');
    
    if (response.data.success) {
      // Сохраняем в кеш
      settingsCache = {
        data: response.data.data,
        timestamp: Date.now(),
      };
      return response.data.data;
    }
    
    return {};
  } catch (error) {
    console.error('Failed to fetch settings:', error);
    return {};
  }
}
