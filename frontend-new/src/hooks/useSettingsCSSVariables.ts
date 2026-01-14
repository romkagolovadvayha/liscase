'use client';

import { useEffect } from 'react';
import { useSettings } from './useSettings';
import { formatImageUrl } from '@/lib/utils/imageUrl';

/**
 * Хук для обновления CSS переменных из настроек API
 * Обновляет все изображения и другие настройки design/colors в CSS переменных
 */
export function useSettingsCSSVariables() {
  const { data: settings } = useSettings();

  useEffect(() => {
    if (!settings || typeof window === 'undefined') {
      return;
    }

    const root = document.documentElement;
    
    // Получаем CDN URL из настроек
    const cdnUrl = settings.site?.cdnUrl as string | null | undefined;

    // Функция для получения значения настройки из вложенной структуры
    const getSettingValue = (category: string, key: string): string | null => {
      // Пробуем разные варианты доступа к настройкам
      // 1. Вложенная структура по категориям (как возвращает API)
      if (settings[category] && typeof settings[category] === 'object' && settings[category][key]) {
        return settings[category][key] as string;
      }
      
      // 2. Пробуем найти с разными вариантами ключа (camelCase, snake_case)
      const camelKey = key.replace(/_([a-z])/g, (_, letter) => letter.toUpperCase());
      const snakeKey = key.replace(/([A-Z])/g, '_$1').toLowerCase();
      
      if (settings[category] && typeof settings[category] === 'object') {
        if (settings[category][camelKey]) {
          return settings[category][camelKey] as string;
        }
        if (settings[category][snakeKey]) {
          return settings[category][snakeKey] as string;
        }
      }
      
      return null;
    };

    // Функция для установки CSS переменной с изображением
    const setImageVariable = (varName: string, category: string, key: string, defaultValue: string) => {
      const value = getSettingValue(category, key) || defaultValue;
      if (value) {
        // Если значение уже полный URL (начинается с http), используем как есть
        // Иначе форматируем с учетом CDN
        const formattedUrl = value.startsWith('http://') || value.startsWith('https://') 
          ? value 
          : formatImageUrl(value, cdnUrl);
        root.style.setProperty(varName, `url('${formattedUrl}')`);
      }
    };

    // Функция для установки CSS переменной с видео
    const setVideoVariable = (varName: string, category: string, key: string, defaultValue: string) => {
      const value = getSettingValue(category, key) || defaultValue;
      if (value) {
        // Если значение уже полный URL (начинается с http), используем как есть
        // Иначе форматируем с учетом CDN
        const formattedUrl = value.startsWith('http://') || value.startsWith('https://') 
          ? value 
          : formatImageUrl(value, cdnUrl);
        root.style.setProperty(varName, formattedUrl);
      }
    };

    // Обновляем все изображения из настроек design (используем реальные ключи из API)
    setImageVariable('--logo', 'design', 'logo', '/uploads/site/design/0554f1c40e29411f9422851a1918153c.svg');
    setImageVariable('--favicon', 'design', 'favicon', '/uploads/site/design/273ce7b2b2b39b5df9a65d75d0b2b49a.svg');
    setImageVariable('--statsBlockImage', 'design', 'statsBlockImage', '/uploads/site/design/05bcff0b3d97800f770da90e6b0dd7a4.png');
    setImageVariable('--bonusBlockImage', 'design', 'bonusBlockImage', '/uploads/site/design/9480975c085eaba7beef190ecb12c045.png');
    setImageVariable('--promoPopupImage', 'design', 'promoPopupImage', '/uploads/site/design/ed51829cce613849269a12c3e117e1bf.png');
    setImageVariable('--payPopupImage', 'design', 'payPopupImage', '/uploads/site/design/3cb8da4cc81f5f0836121ad696e51911.png');
    setImageVariable('--wipeBlockPopupImage', 'design', 'wipeBlockPopupImage', '/uploads/site/design/a9c5acfa1cbb82d174f22696a9086f1c.png');
    setImageVariable('--icon-money', 'design', 'icon_money', '/uploads/site/design/72d342d54b58fdf14adc5bd6ea00b994.svg');
    setImageVariable('--image-not-auth', 'design', 'image_not_auth', '/uploads/site/design/35ffaaecf35b4348b2438718ebfe5d37.png');
    setImageVariable('--iconskins', 'design', 'iconskins', '/uploads/site/design/5fbb804ed4015b8283707b0e080ed839.svg');
    setImageVariable('--servers-image', 'design', 'servers_image', '/uploads/site/design/8aa94e4f99ab7e3abb1972ce8150fd20.png');
    setImageVariable('--watemark', 'design', 'watemark', '/uploads/site/design/d83ad05567daae70fe32228c441ace9c.png');
    setImageVariable('--avatar-default', 'design', 'avatar_default', '/uploads/site/design/86e6c084c19ad0c4c824c8e985b3bc8c.png');
    setImageVariable('--year-review', 'design', 'year_review', '/uploads/site/design/1200d080a8b33fd4b94c5d20c8ff4154.png');

    // Обновляем изображения из настроек colors (если они есть в API)
    // setImageVariable('--light-the-best-background', 'colors', 'lightTheBestBackground', '/uploads/site/colors/0a7b25a64742af33841f6b08ab3d7820.svg');
    // setImageVariable('--light-background', 'colors', 'lightBackground', '/uploads/site/colors/811d0f50009f072bf3c00ab56fa6aaf4.svg');
    // setImageVariable('--indicator-online', 'colors', 'indicatorOnline', '/uploads/site/colors/29e31f1a394723cc94f22128cd0f3ea4.svg');
    // setImageVariable('--categories-image-shadow', 'colors', 'categoriesImageShadow', '/uploads/site/colors/7864300184aa67de3a3c193391c9cbf0.svg');
    // setImageVariable('--categories-image-glow', 'colors', 'categoriesImageGlow', '/uploads/site/colors/2b08faaace1d26d11f676cfb9523cb40.svg');

    // Обновляем видео
    setVideoVariable('--bonusBlockImageVideo', 'design', 'bonusBlockImageVideo', '/uploads/site/design/e9b68543541012c0111110f300ef73eb.webm');
    setVideoVariable('--statsBlockImageVideo', 'design', 'statsBlockImageVideo', '/uploads/site/design/69dc6cddf4ee2b0cfff304e3a6aed89d.webm');
  }, [settings]);
}

