import { query } from '@/lib/db';

interface SiteSetting {
  id: number;
  name: string;
  category: string;
  type: string;
  value: string;
  code: string;
  is_translate: number;
}

interface SettingsCache {
  data: Record<string, any>;
  timestamp: number;
}

// Кеш на 3 часа (как в старой версии)
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
    const settings = await query<SiteSetting>(`
      SELECT 
        id,
        name,
        category,
        type,
        value,
        code,
        is_translate
      FROM site_settings
    `);

    const result: Record<string, any> = {};
    const seenKeys = new Set<string>();

    // Формируем ключи как category_code и обрабатываем типы
    settings.forEach((item) => {
      // Пропускаем записи без category или code
      if (!item.category || !item.code) {
        return;
      }

      // Ключ формируется как category_code (уникальная комбинация category + code)
      const key = `${item.category}_${item.code}`;
      
      // Предупреждаем о дубликатах
      if (seenKeys.has(key)) {
        console.warn(`Duplicate setting key found: ${key}. Last value will be used.`);
      }
      seenKeys.add(key);

      let value: any = item.value;

      // Обработка типов (как в методе getValue() модели)
      if (item.type === 'checkbox') {
        value = item.value === '1';
      } else if (item.type === 'number') {
        value = item.value ? parseFloat(item.value) : 0;
      } else {
        value = item.value || '';
      }

      result[key] = value;
    });

    // Сохраняем в кеш
    settingsCache = {
      data: result,
      timestamp: Date.now(),
    };

    return result;
  } catch (error) {
    console.error('Error fetching settings:', error);
    // Возвращаем кеш, если есть, или пустой объект
    return settingsCache?.data || {};
  }
}

/**
 * Очистить кеш настроек
 */
export function clearSettingsCache(): void {
  settingsCache = null;
}

