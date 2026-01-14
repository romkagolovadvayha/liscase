/**
 * Утилита для получения изображений из настроек API
 * Все изображения должны браться из настроек design и colors, а не из относительных путей
 */

import { formatImageUrl } from './imageUrl';

/**
 * Получить изображение из настроек по ключу
 * @param settings - объект настроек из API
 * @param key - ключ настройки (например: 'design_logo', 'design_avatar')
 * @param defaultValue - значение по умолчанию (fallback)
 * @param cdnUrl - CDN URL для форматирования
 * @returns URL изображения
 */
export function getSettingImage(
  settings: Record<string, any> | null | undefined,
  category: string,
  key: string,
  defaultValue: string = '',
  cdnUrl?: string | null
): string {
  if (!settings) {
    return formatImageUrl(defaultValue, cdnUrl);
  }

  // Функция для получения значения из вложенной структуры
  const getValue = (): string | null => {
    // 1. Вложенная структура по категориям (как возвращает API)
    if (settings[category] && typeof settings[category] === 'object') {
      // Пробуем разные варианты ключа (camelCase, snake_case, оригинальный)
      if (settings[category][key]) {
        return settings[category][key] as string;
      }
      
      // Преобразуем ключ в разные форматы
      const camelKey = key.replace(/_([a-z])/g, (_, letter) => letter.toUpperCase());
      const snakeKey = key.replace(/([A-Z])/g, '_$1').toLowerCase();
      
      if (settings[category][camelKey]) {
        return settings[category][camelKey] as string;
      }
      if (settings[category][snakeKey]) {
        return settings[category][snakeKey] as string;
      }
    }
    
    // 2. Пробуем плоский доступ (для обратной совместимости)
    const flatKey = `${category}_${key}`;
    if (settings[flatKey]) {
      return settings[flatKey] as string;
    }
    
    return null;
  };

  const value = getValue();
  
  if (value) {
    // Если значение уже полный URL, используем как есть
    if (value.startsWith('http://') || value.startsWith('https://')) {
      return value;
    }
    return formatImageUrl(value, cdnUrl);
  }

  return formatImageUrl(defaultValue, cdnUrl);
}

/**
 * Получить логотип из настроек
 */
export function getLogo(settings: Record<string, any> | null | undefined, cdnUrl?: string | null): string {
  return getSettingImage(
    settings,
    'design',
    'logo',
    '/uploads/site/design/0554f1c40e29411f9422851a1918153c.svg',
    cdnUrl
  );
}

/**
 * Получить аватар по умолчанию из настроек
 */
export function getDefaultAvatar(settings: Record<string, any> | null | undefined, cdnUrl?: string | null): string {
  return getSettingImage(
    settings,
    'design',
    'avatar_default',
    '/uploads/site/design/86e6c084c19ad0c4c824c8e985b3bc8c.png',
    cdnUrl
  );
}

/**
 * Получить изображение для блока статистики
 */
export function getStatsImage(settings: Record<string, any> | null | undefined, cdnUrl?: string | null): string {
  return getSettingImage(
    settings,
    'design',
    'statsBlockImage',
    '/uploads/site/design/05bcff0b3d97800f770da90e6b0dd7a4.png',
    cdnUrl
  );
}

/**
 * Получить изображение для блока бонусов
 */
export function getBonusImage(settings: Record<string, any> | null | undefined, cdnUrl?: string | null): string {
  return getSettingImage(
    settings,
    'design',
    'bonusBlockImage',
    '/uploads/site/design/9480975c085eaba7beef190ecb12c045.png',
    cdnUrl
  );
}

/**
 * Получить видео для блока статистики
 */
export function getStatsImageVideo(settings: Record<string, any> | null | undefined, cdnUrl?: string | null): string {
  return getSettingImage(
    settings,
    'design',
    'statsBlockImageVideo',
    '/uploads/site/design/69dc6cddf4ee2b0cfff304e3a6aed89d.webm',
    cdnUrl
  );
}

/**
 * Получить видео для блока бонусов
 */
export function getBonusImageVideo(settings: Record<string, any> | null | undefined, cdnUrl?: string | null): string {
  return getSettingImage(
    settings,
    'design',
    'bonusBlockImageVideo',
    '/uploads/site/design/e9b68543541012c0111110f300ef73eb.webm',
    cdnUrl
  );
}

/**
 * Получить изображение для попапа промо
 */
export function getPromoPopupImage(settings: Record<string, any> | null | undefined, cdnUrl?: string | null): string {
  return getSettingImage(
    settings,
    'design',
    'promoPopupImage',
    '/uploads/site/design/ed51829cce613849269a12c3e117e1bf.png',
    cdnUrl
  );
}

/**
 * Получить изображение для попапа оплаты
 */
export function getPayPopupImage(settings: Record<string, any> | null | undefined, cdnUrl?: string | null): string {
  return getSettingImage(
    settings,
    'design',
    'payPopupImage',
    '/uploads/site/design/3cb8da4cc81f5f0836121ad696e51911.png',
    cdnUrl
  );
}

/**
 * Получить изображение для блока вайпа
 */
export function getWipeBlockPopupImage(settings: Record<string, any> | null | undefined, cdnUrl?: string | null): string {
  return getSettingImage(
    settings,
    'design',
    'wipeBlockPopupImage',
    '/uploads/site/design/a9c5acfa1cbb82d174f22696a9086f1c.png',
    cdnUrl
  );
}

/**
 * Получить изображение для неавторизованного пользователя
 */
export function getNotAuthImage(settings: Record<string, any> | null | undefined, cdnUrl?: string | null): string {
  return getSettingImage(
    settings,
    'design',
    'image_not_auth',
    '/uploads/site/design/35ffaaecf35b4348b2438718ebfe5d37.png',
    cdnUrl
  );
}

/**
 * Получить изображение для серверов
 */
export function getServersImage(settings: Record<string, any> | null | undefined, cdnUrl?: string | null): string {
  return getSettingImage(
    settings,
    'design',
    'servers_image',
    '/uploads/site/design/8aa94e4f99ab7e3abb1972ce8150fd20.png',
    cdnUrl
  );
}

/**
 * Получить изображение для модального окна (light effect)
 */
export function getModalLightImage(settings: Record<string, any> | null | undefined, cdnUrl?: string | null): string {
  // Это изображение может не быть в API, используем дефолтное значение
  return getSettingImage(
    settings,
    'design',
    'modalLight',
    '/images/design/modal/light.png',
    cdnUrl
  );
}

