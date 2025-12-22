'use client';

import React, { useState, useEffect } from 'react';
import Button from '@/components/forms/Button';

type Theme = 'original' | 'glamour' | 'winter' | 'summer' | 'dark';

export default function ThemeSwitcher() {
  const [currentTheme, setCurrentTheme] = useState<Theme>('original');

  useEffect(() => {
    // Применяем тему при загрузке
    const root = document.documentElement;
    if (currentTheme === 'original') {
      root.removeAttribute('data-theme');
    } else {
      root.setAttribute('data-theme', currentTheme);
    }
  }, [currentTheme]);

  const handleThemeChange = (theme: Theme) => {
    setCurrentTheme(theme);
  };

  const themes: { id: Theme; label: string; emoji: string }[] = [
    { id: 'original', label: 'Оригинальный', emoji: '🎨' },
    { id: 'glamour', label: 'Гламурный', emoji: '💅' },
    { id: 'winter', label: 'Зимний', emoji: '❄️' },
    { id: 'summer', label: 'Летний', emoji: '☀️' },
    { id: 'dark', label: 'Темный', emoji: '🌙' },
  ];

  return (
    <div className="theme-switcher">
      <div className="theme-switcher__label">Цветовая гамма:</div>
      <div className="theme-switcher__buttons">
        {themes.map((theme) => (
          <Button
            key={theme.id}
            variant={currentTheme === theme.id ? 'primary' : 'secondary'}
            onClick={() => handleThemeChange(theme.id)}
            style={{ minWidth: '140px' }}
          >
            {theme.emoji} {theme.label}
          </Button>
        ))}
      </div>
    </div>
  );
}

