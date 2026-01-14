'use client';

import { useEffect, useState } from 'react';

/**
 * Хук для получения значения CSS переменной
 * @param variableName - имя CSS переменной (без --)
 * @param defaultValue - значение по умолчанию
 * @returns значение CSS переменной или значение по умолчанию
 */
export function useCSSVariable(variableName: string, defaultValue: string = ''): string {
  const [value, setValue] = useState<string>(defaultValue);

  useEffect(() => {
    if (typeof window !== 'undefined') {
      const updateValue = () => {
        const cssValue = getComputedStyle(document.documentElement)
          .getPropertyValue(`--${variableName}`)
          .trim();
        
        // Убираем url() и кавычки из значения
        const cleanValue = cssValue
          .replace(/^url\(/, '')
          .replace(/\)$/, '')
          .replace(/^['"]/, '')
          .replace(/['"]$/, '');
        
        setValue(cleanValue || defaultValue);
      };
      
      // Обновляем значение сразу
      updateValue();
      
      // Создаем MutationObserver для отслеживания изменений CSS переменных
      const observer = new MutationObserver(updateValue);
      observer.observe(document.documentElement, {
        attributes: true,
        attributeFilter: ['style'],
      });
      
      // Также проверяем периодически (на случай, если изменения не отслеживаются)
      const interval = setInterval(updateValue, 100);
      
      return () => {
        observer.disconnect();
        clearInterval(interval);
      };
    }
  }, [variableName, defaultValue]);

  return value;
}



















